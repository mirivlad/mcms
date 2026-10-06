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
use MCMS\Modules\Orders\Services\Orders;
use MCMS\Modules\Stock\Services\Stock;

final class OrdersModuleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\\Settings'];

    private ModuleManager $manager;
    private ModuleServiceRegistry $services;
    private Stock $stock;
    private CartService $cart;
    private Orders $orders;
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
        $this->manager->enable('orders');
        $this->manager->bootEnabled();

        $catalogue = new Catalogue($this->db, $this->services->get('product-images.library'));
        $this->stock = new Stock($this->db, $catalogue);
        $this->cart = new CartService($catalogue, $this->stock);
        $this->orders = new Orders($this->db, $this->cart, $this->stock);

        $now = date('Y-m-d H:i:s');
        $this->db->table('catalogue_products')->insert([
            'title' => 'Coffee',
            'slug' => 'coffee-order-test',
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
            'sku' => 'ORDER-250',
            'label' => '250 g',
            'price_minor' => 15000,
            'currency' => 'RUB',
            'position' => 0,
        ]);
        $this->firstVariant = (int) $this->db->insertID();

        $this->db->table('catalogue_variants')->insert([
            'product_id' => $productId,
            'sku' => 'ORDER-500',
            'label' => '500 g',
            'price_minor' => 28000,
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
        $this->migrations->setNamespace('MCMS\\Modules\\Orders')->regress(0, 'tests');
        $this->migrations->setNamespace('MCMS\\Modules\\Stock')->regress(0, 'tests');
        $this->migrations->setNamespace('MCMS\\Modules\\Catalogue')->regress(0, 'tests');
        $this->migrations->setNamespace('MCMS\\Modules\\ProductImages')->regress(0, 'tests');
        parent::tearDown();
    }

    public function testCheckoutCreatesOrderAndOnlyThenReservesStock(): void
    {
        self::assertTrue($this->services->has('orders.orders'));

        $this->cart->add($this->firstVariant, 2);
        self::assertSame(0, $this->stock->level($this->firstVariant)['reserved']);

        $order = $this->orders->createFromCart([
            'name' => 'Alice',
            'phone' => '+1 555 0100',
            'email' => 'alice@example.test',
            'comment' => 'Call first',
        ]);

        self::assertSame('new', $order['status']);
        self::assertSame('Alice', $order['customer_name']);
        self::assertSame(30000, $order['subtotal_minor']);
        self::assertCount(1, $order['items']);
        self::assertSame('ORDER-250', $order['items'][0]['sku']);
        self::assertSame(2, $order['items'][0]['quantity']);
        self::assertSame(2, $this->stock->level($this->firstVariant)['reserved']);
        self::assertSame(8, $this->stock->level($this->firstVariant)['available']);
        self::assertSame(0, $this->cart->count());
        self::assertSame('new', $order['history'][0]['to_status']);
    }

    public function testChangedAvailabilityDoesNotSilentlyCreateReducedOrder(): void
    {
        $this->stock->setOnHand($this->firstVariant, 5, 1);
        $this->cart->add($this->firstVariant, 4);
        $this->stock->reserve('order:other-test', $this->firstVariant, 3);

        try {
            $this->orders->createFromCart(['name' => 'Bob', 'phone' => '123']);
            self::fail('Order was created from silently adjusted cart.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Availability changed', $e->getMessage());
        }

        self::assertSame(0, $this->db->table('orders')->countAllResults());
        $cart = $this->cart->snapshot();
        self::assertSame(2, $cart['items'][0]['quantity']);
        self::assertSame(3, $this->stock->level($this->firstVariant)['reserved']);
        $this->stock->release('order:other-test');
    }

    public function testCompletingOrderConsumesReservationAndStock(): void
    {
        $this->cart->add($this->firstVariant, 3);
        $order = $this->orders->createFromCart(['name' => 'Carol', 'phone' => '555']);
        self::assertSame(10, $this->stock->level($this->firstVariant)['on_hand']);
        self::assertSame(3, $this->stock->level($this->firstVariant)['reserved']);

        $order = $this->orders->transition($order['id'], 'processing', 42, 'Accepted');
        self::assertSame('processing', $order['status']);
        self::assertSame(3, $this->stock->level($this->firstVariant)['reserved']);

        $order = $this->orders->transition($order['id'], 'completed', 42, 'Delivered');
        self::assertSame('completed', $order['status']);
        self::assertSame(7, $this->stock->level($this->firstVariant)['on_hand']);
        self::assertSame(0, $this->stock->level($this->firstVariant)['reserved']);
        self::assertSame(7, $this->stock->level($this->firstVariant)['available']);
        self::assertNotNull($order['completed_at']);

        $this->expectException(RuntimeException::class);
        $this->orders->transition($order['id'], 'processing', 42);
    }

    public function testCancellingOrderReleasesReservationWithoutWritingOffStock(): void
    {
        $this->cart->add($this->secondVariant, 2);
        $order = $this->orders->createFromCart(['name' => 'Dan', 'phone' => '777']);
        self::assertSame(2, $this->stock->level($this->secondVariant)['reserved']);

        $order = $this->orders->transition($order['id'], 'cancelled', 7, 'Customer cancelled');
        self::assertSame('cancelled', $order['status']);
        self::assertSame(6, $this->stock->level($this->secondVariant)['on_hand']);
        self::assertSame(0, $this->stock->level($this->secondVariant)['reserved']);
        self::assertSame(6, $this->stock->level($this->secondVariant)['available']);
        self::assertNotNull($order['cancelled_at']);
    }

    public function testActiveOrderKeepsVariantLockUntilTerminalStatus(): void
    {
        $this->cart->add($this->firstVariant, 1);
        $order = $this->orders->createFromCart(['name' => 'Guard', 'phone' => '333']);

        $lock = $this->db->table('order_variant_locks')->where('order_id', $order['id'])->get()->getRowArray();
        self::assertNotNull($lock);
        self::assertSame($this->firstVariant, (int) $lock['variant_id']);

        $this->orders->transition($order['id'], 'cancelled', 5);
        self::assertSame(0, $this->db->table('order_variant_locks')->where('order_id', $order['id'])->countAllResults());
    }

    public function testManagerListSupportsStatusAndCustomerSearch(): void
    {
        $this->cart->add($this->firstVariant, 1);
        $first = $this->orders->createFromCart(['name' => 'Elena', 'phone' => '100']);
        $this->orders->transition($first['id'], 'processing', 9);

        $this->cart->add($this->secondVariant, 1);
        $second = $this->orders->createFromCart(['name' => 'Frank', 'phone' => '200']);

        self::assertSame(1, $this->orders->count('Elena', 'processing'));
        self::assertSame($first['id'], $this->orders->list('Elena', 'processing')[0]['id']);
        self::assertSame(1, $this->orders->count((string) $second['id'], 'new'));
        self::assertSame($second['id'], $this->orders->list((string) $second['id'], 'new')[0]['id']);
    }
}
