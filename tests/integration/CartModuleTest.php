<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Core\Navigation\NavigableResourceRegistry;
use MCMS\Modules\Cart\Services\Cart as CartService;
use MCMS\Modules\Catalogue\Services\Catalogue;
use MCMS\Modules\Stock\Services\Stock;

final class CartModuleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\\Settings'];

    private ModuleManager $manager;
    private ModuleServiceRegistry $services;
    private Stock $stock;
    private CartService $cart;
    private int $productId;
    private int $firstVariant;
    private int $secondVariant;

    protected function setUp(): void
    {
        parent::setUp();
        service('language')->setLocale('en');
        session()->remove('mcms_cart');

        $this->services = new ModuleServiceRegistry();
        $this->manager = new ModuleManager(
            $this->db,
            new AdminRegistry(),
            $this->services,
            ROOTPATH . 'modules',
            new NavigableResourceRegistry()
        );
        $this->manager->enable('cart');
        $this->manager->bootEnabled();

        $catalogue = new Catalogue($this->db, $this->services->get('product-images.library'));
        $this->stock = new Stock($this->db, $catalogue);
        $this->cart = new CartService($catalogue, $this->stock);

        $now = date('Y-m-d H:i:s');
        $this->db->table('catalogue_products')->insert([
            'title' => 'Tea',
            'slug' => 'tea-cart-test',
            'category' => '',
            'category_id' => null,
            'summary' => '',
            'body' => '',
            'status' => 'published',
            'image_set_id' => bin2hex(random_bytes(16)),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->productId = (int) $this->db->insertID();

        $this->db->table('catalogue_variants')->insert([
            'product_id' => $this->productId,
            'sku' => 'TEA-100',
            'label' => '100 g',
            'price_minor' => 125005,
            'currency' => 'RUB',
            'position' => 0,
        ]);
        $this->firstVariant = (int) $this->db->insertID();

        $this->db->table('catalogue_variants')->insert([
            'product_id' => $this->productId,
            'sku' => 'TEA-250',
            'label' => '250 g',
            'price_minor' => 240000,
            'currency' => 'RUB',
            'position' => 1,
        ]);
        $this->secondVariant = (int) $this->db->insertID();

        $this->stock->setOnHand($this->firstVariant, 10, 1);
        $this->stock->setOnHand($this->secondVariant, 6, 1);
    }

    protected function tearDown(): void
    {
        session()->remove('mcms_cart');
        $this->migrations->setNamespace('MCMS\\Modules\\Stock')->regress(0, 'tests');
        $this->migrations->setNamespace('MCMS\\Modules\\Catalogue')->regress(0, 'tests');
        $this->migrations->setNamespace('MCMS\\Modules\\ProductImages')->regress(0, 'tests');
        parent::tearDown();
    }

    public function testCartNeverReservesStockAndKeepsPriceSnapshot(): void
    {
        self::assertTrue($this->services->has('cart.cart'));

        $result = $this->cart->add($this->firstVariant, 3);
        self::assertFalse($result['adjusted']);
        self::assertSame(3, $result['quantity']);

        $level = $this->stock->level($this->firstVariant);
        self::assertSame(0, $level['reserved']);
        self::assertSame(10, $level['available']);

        $this->db->table('catalogue_variants')->where('id', $this->firstVariant)
            ->update(['price_minor' => 999999, 'sku' => 'TEA-CHANGED']);

        $snapshot = $this->cart->snapshot();
        self::assertSame('TEA-100', $snapshot['items'][0]['sku']);
        self::assertSame(125005, $snapshot['items'][0]['unit_price_minor']);
        self::assertSame(375015, $snapshot['subtotal_minor']);
        self::assertSame('3 750,15 ₽', $snapshot['subtotal_display']);
        self::assertSame(0, $this->stock->level($this->firstVariant)['reserved']);
    }

    public function testQuantityIsLimitedByCurrentAvailabilityWithoutReservation(): void
    {
        $this->stock->setOnHand($this->firstVariant, 5, 1);

        self::assertSame(3, $this->cart->add($this->firstVariant, 3)['quantity']);
        $result = $this->cart->add($this->firstVariant, 10);
        self::assertTrue($result['adjusted']);
        self::assertSame(5, $result['quantity']);
        self::assertSame(0, $this->stock->level($this->firstVariant)['reserved']);
        self::assertSame(5, $this->stock->level($this->firstVariant)['available']);

        $result = $this->cart->setQuantity($this->firstVariant, 2);
        self::assertFalse($result['adjusted']);
        self::assertSame(2, $result['quantity']);
        self::assertSame(5, $this->stock->level($this->firstVariant)['available']);
    }

    public function testOpeningCartClampsQuantityWhenAnotherOrderReservedStock(): void
    {
        $this->stock->setOnHand($this->firstVariant, 5, 1);
        $this->cart->add($this->firstVariant, 4);

        $this->stock->reserve('order:other', $this->firstVariant, 3);
        $snapshot = $this->cart->snapshot();

        self::assertCount(1, $snapshot['items']);
        self::assertTrue($snapshot['items'][0]['stock_adjusted']);
        self::assertSame(4, $snapshot['items'][0]['requested_quantity']);
        self::assertSame(2, $snapshot['items'][0]['quantity']);
        self::assertSame(3, $this->stock->level($this->firstVariant)['reserved']);
        self::assertSame(2, $this->stock->level($this->firstVariant)['available']);

        $this->cart->clear();
        self::assertSame(3, $this->stock->level($this->firstVariant)['reserved']);
        $this->stock->release('order:other');
    }

    public function testRemovingCartItemsDoesNotTouchStockReservations(): void
    {
        $this->stock->reserve('order:existing', $this->secondVariant, 2);
        $this->cart->add($this->secondVariant, 3);
        $this->cart->remove($this->secondVariant);

        self::assertSame(0, $this->cart->count());
        self::assertSame(2, $this->stock->level($this->secondVariant)['reserved']);
        self::assertSame(4, $this->stock->level($this->secondVariant)['available']);
        $this->stock->release('order:existing');
    }

    public function testUnpublishedProductIsRemovedDuringReconciliation(): void
    {
        $this->cart->add($this->firstVariant, 2);
        $this->db->table('catalogue_products')->where('id', $this->productId)->update(['status' => 'draft']);

        $snapshot = $this->cart->snapshot();

        self::assertSame([], $snapshot['items']);
        self::assertSame(0, $snapshot['total_items']);
        self::assertSame(0, $this->stock->level($this->firstVariant)['reserved']);
        self::assertSame(10, $this->stock->level($this->firstVariant)['available']);
    }
}
