<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Core\Navigation\NavigableResourceRegistry;
use MCMS\Modules\Catalogue\Services\Catalogue;
use MCMS\Modules\Stock\Api\StockInterface;
use MCMS\Modules\Stock\Services\Stock;

final class StockModuleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\\Settings'];

    private ModuleManager $manager;
    private ModuleServiceRegistry $services;
    private StockInterface $stock;
    private int $firstVariant;
    private int $secondVariant;

    protected function setUp(): void
    {
        parent::setUp();
        service('language')->setLocale('en');

        $this->services = new ModuleServiceRegistry();
        $this->manager = new ModuleManager(
            $this->db,
            new AdminRegistry(),
            $this->services,
            ROOTPATH . 'modules',
            new NavigableResourceRegistry()
        );
        $this->manager->enable('stock');
        $this->manager->bootEnabled();
        // Module services use the application's shared connection. The test database is an
        // isolated in-memory SQLite connection, so exercise Stock against that same connection.
        $this->stock = new Stock(
            $this->db,
            new Catalogue($this->db, $this->services->get('product-images.library'))
        );

        $now = date('Y-m-d H:i:s');
        $this->db->table('catalogue_products')->insert([
            'title' => 'Coffee',
            'slug' => 'coffee-stock-test',
            'category' => '',
            'category_id' => null,
            'summary' => '',
            'body' => '',
            'status' => 'published',
            'image_set_id' => bin2hex(random_bytes(16)),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $productId = (int) $this->db->insertID();

        $this->db->table('catalogue_variants')->insert([
            'product_id' => $productId,
            'sku' => 'STOCK-250',
            'label' => '250 g',
            'price_minor' => 10000,
            'currency' => 'RUB',
            'position' => 0,
        ]);
        $this->firstVariant = (int) $this->db->insertID();

        $this->db->table('catalogue_variants')->insert([
            'product_id' => $productId,
            'sku' => 'STOCK-500',
            'label' => '500 g',
            'price_minor' => 18000,
            'currency' => 'RUB',
            'position' => 1,
        ]);
        $this->secondVariant = (int) $this->db->insertID();
    }

    protected function tearDown(): void
    {
        $this->migrations->setNamespace('MCMS\\Modules\\Stock')->regress(0, 'tests');
        $this->migrations->setNamespace('MCMS\\Modules\\Catalogue')->regress(0, 'tests');
        $this->migrations->setNamespace('MCMS\\Modules\\ProductImages')->regress(0, 'tests');
        parent::tearDown();
    }

    public function testModuleBoundarySearchAndManualAdjustment(): void
    {
        self::assertTrue($this->services->has('catalogue.repository'));
        self::assertTrue($this->services->has('stock.inventory'));

        $catalogue = $this->services->get('catalogue.repository');
        self::assertSame($this->firstVariant, $catalogue->findVariant($this->firstVariant)['id']);
        self::assertSame(2, $catalogue->countVariants('STOCK'));
        self::assertSame(1, $catalogue->countVariants('500 g'));

        self::assertSame(0, $this->stock->level($this->firstVariant)['available']);
        $level = $this->stock->setOnHand($this->firstVariant, 12, 7, 'delivery');
        self::assertSame(['on_hand' => 12, 'reserved' => 0, 'available' => 12], [
            'on_hand' => $level['on_hand'], 'reserved' => $level['reserved'], 'available' => $level['available'],
        ]);

        $rows = $this->stock->list('250', 50, 0);
        self::assertCount(1, $rows);
        self::assertSame('Coffee', $rows[0]['product_title']);
        self::assertSame(12, $rows[0]['stock']['on_hand']);

        $movement = $this->db->table('stock_movements')->where('variant_id', $this->firstVariant)->get()->getRowArray();
        self::assertSame(12, (int) $movement['change_qty']);
        self::assertSame(7, (int) $movement['user_id']);
        self::assertSame('delivery', $movement['note']);
    }

    public function testReservationsProtectAvailabilityReleaseExpireAndConsume(): void
    {
        $this->stock->setOnHand($this->firstVariant, 10, 1);

        $level = $this->stock->reserve('cart:one', $this->firstVariant, 4, new DateTimeImmutable('+1 hour'));
        self::assertSame(4, $level['reserved']);
        self::assertSame(6, $level['available']);

        $level = $this->stock->reserve('cart:one', $this->firstVariant, 6, new DateTimeImmutable('+1 hour'));
        self::assertSame(6, $level['reserved']);
        self::assertSame(4, $level['available']);

        try {
            $this->stock->setOnHand($this->firstVariant, 5, 1);
            self::fail('Stock was reduced below reserved quantity.');
        } catch (InvalidArgumentException) {
            self::assertSame(10, $this->stock->level($this->firstVariant)['on_hand']);
        }

        try {
            $this->stock->reserve('cart:two', $this->firstVariant, 5);
            self::fail('Over-reservation was accepted.');
        } catch (RuntimeException) {
            self::assertSame(4, $this->stock->level($this->firstVariant)['available']);
        }

        $this->stock->release('cart:one', $this->firstVariant);
        self::assertSame(10, $this->stock->level($this->firstVariant)['available']);

        $this->stock->reserve('order:one', $this->firstVariant, 3, new DateTimeImmutable('+1 hour'));
        $this->stock->consume('order:one');
        $level = $this->stock->level($this->firstVariant);
        self::assertSame(7, $level['on_hand']);
        self::assertSame(0, $level['reserved']);
        self::assertSame(7, $level['available']);

        $this->stock->reserve('cart:expired', $this->firstVariant, 2, new DateTimeImmutable('+1 hour'));
        $this->db->table('stock_reservations')->where('reservation_key', 'cart:expired')
            ->update(['expires_at' => '2000-01-01 00:00:00']);
        self::assertSame(0, $this->stock->level($this->firstVariant)['reserved']);
    }

    public function testVariantDeletionCascadesLiveStockButKeepsAudit(): void
    {
        $this->stock->setOnHand($this->secondVariant, 8, 1, 'initial');
        $this->stock->reserve('cart:delete', $this->secondVariant, 2);

        $this->db->table('catalogue_variants')->where('id', $this->secondVariant)->delete();

        self::assertSame(0, $this->db->table('stock_levels')->where('variant_id', $this->secondVariant)->countAllResults());
        self::assertSame(0, $this->db->table('stock_reservations')->where('variant_id', $this->secondVariant)->countAllResults());
        self::assertSame(1, $this->db->table('stock_movements')->where('variant_id', $this->secondVariant)->countAllResults());
    }
}
