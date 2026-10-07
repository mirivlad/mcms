<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Core\Navigation\NavigableResourceRegistry;
use MCMS\Modules\Delivery\Api\DeliveryInterface;
use MCMS\Modules\Delivery\Services\Delivery;

final class DeliveryModuleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\\Settings'];

    private ModuleManager $manager;
    private ModuleServiceRegistry $services;
    private DeliveryInterface $delivery;

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
        $this->manager->enable('delivery');
        $this->manager->bootEnabled();
        $this->delivery = $this->services->get('delivery.methods');
    }

    protected function tearDown(): void
    {
        $this->migrations->setNamespace('MCMS\\Modules\\Delivery')->regress(0, 'tests');
        $this->migrations->setNamespace('MCMS\\Modules\\Orders')->regress(0, 'tests');
        $this->migrations->setNamespace('MCMS\\Modules\\Stock')->regress(0, 'tests');
        $this->migrations->setNamespace('MCMS\\Modules\\Catalogue')->regress(0, 'tests');
        $this->migrations->setNamespace('MCMS\\Modules\\ProductImages')->regress(0, 'tests');
        parent::tearDown();
    }

    public function testModuleBoundaryAndDependencies(): void
    {
        self::assertTrue($this->services->has('delivery.methods'));
        self::assertTrue($this->services->has('orders.orders'));
        self::assertTrue($this->services->has('cart.cart'));
        self::assertTrue($this->services->has('stock.inventory'));
    }

    public function testDeliveryAndPickupCrudUseIntegerMoney(): void
    {
        $delivery = $this->delivery->save(null, [
            'name' => 'Courier',
            'type' => 'delivery',
            'price' => '450,50',
            'enabled' => '1',
            'position' => '20',
        ]);
        $pickup = $this->delivery->save(null, [
            'name' => 'Farm pickup',
            'type' => 'pickup',
            'price' => '0',
            'enabled' => '1',
            'position' => '10',
        ]);

        self::assertSame(45050, $delivery['price_minor']);
        self::assertSame('450,50 ₽', $delivery['price_display']);
        self::assertSame('delivery', $delivery['type']);
        self::assertSame(0, $pickup['price_minor']);
        self::assertSame('Pickup', $pickup['type_label']);

        $selectable = $this->delivery->selectable();
        self::assertSame([$pickup['id'], $delivery['id']], array_column($selectable, 'id'));

        $delivery = $this->delivery->save($delivery['id'], [
            'name' => 'Courier city',
            'type' => 'delivery',
            'price' => '500.00',
            'enabled' => '0',
            'position' => '5',
        ]);
        self::assertFalse($delivery['enabled']);
        self::assertSame(50000, $delivery['price_minor']);
        self::assertNull($this->delivery->findSelectable($delivery['id']));
        self::assertCount(1, $this->delivery->selectable());

        $this->delivery->delete($pickup['id']);
        self::assertNull($this->delivery->find($pickup['id']));
    }

    public function testReorderChangesDisplayOrderWithoutChangingPrices(): void
    {
        $first = $this->delivery->save(null, [
            'name' => 'Delivery A',
            'type' => 'delivery',
            'price' => '100',
            'enabled' => '1',
            'position' => '10',
        ]);
        $second = $this->delivery->save(null, [
            'name' => 'Pickup B',
            'type' => 'pickup',
            'price' => '25',
            'enabled' => '1',
            'position' => '20',
        ]);

        $this->delivery->reorder([
            $first['id'] => '50',
            $second['id'] => '5',
        ]);

        $all = $this->delivery->all();
        self::assertSame([$second['id'], $first['id']], array_column($all, 'id'));
        self::assertSame(2500, $all[0]['price_minor']);
        self::assertSame(10000, $all[1]['price_minor']);
    }

    public function testPriceParserRejectsFloatsBeyondTwoDecimals(): void
    {
        self::assertSame(12345, Delivery::priceMinor('123,45'));

        $this->expectException(InvalidArgumentException::class);
        Delivery::priceMinor('12.345');
    }
}
