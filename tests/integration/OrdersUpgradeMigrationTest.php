<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use MCMS\Modules\Orders\Database\Migrations\AddFulfillmentSnapshot;
use MCMS\Modules\Orders\Database\Migrations\CreateOrders;

final class OrdersUpgradeMigrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = [
        'App',
        'CodeIgniter\\Settings',
        'MCMS\\Modules\\ProductImages',
        'MCMS\\Modules\\Catalogue',
        'MCMS\\Modules\\Stock',
    ];

    private CreateOrders $createOrders;
    private AddFulfillmentSnapshot $upgradeOrders;

    protected function setUp(): void
    {
        parent::setUp();

        require_once ROOTPATH . 'modules/Orders/Database/Migrations/2026-10-06-010000_CreateOrders.php';
        require_once ROOTPATH . 'modules/Orders/Database/Migrations/2026-10-07-020000_AddFulfillmentSnapshot.php';

        $forge = Database::forge($this->db);
        $this->createOrders = new CreateOrders($forge);
        $this->upgradeOrders = new AddFulfillmentSnapshot($forge);
        $this->createOrders->up();
    }

    protected function tearDown(): void
    {
        if ($this->db->tableExists('orders')) {
            if ($this->db->fieldExists('total_minor', 'orders')) {
                $this->upgradeOrders->down();
            }
            $this->createOrders->down();
        }

        parent::tearDown();
    }

    public function testExistingOrdersAreBackfilledWhenUpgradingToOrders02(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('orders')->insert([
            'status' => 'new',
            'customer_name' => 'Existing customer',
            'phone' => '123',
            'email' => null,
            'comment' => null,
            'subtotal_minor' => 12345,
            'currency' => 'RUB',
            'reservation_key' => 'order:legacy-upgrade',
            'customer_user_id' => null,
            'manager_user_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'completed_at' => null,
            'cancelled_at' => null,
        ]);
        $orderId = (int) $this->db->insertID();

        $this->upgradeOrders->up();

        $row = $this->db->table('orders')->where('id', $orderId)->get()->getRowArray();
        self::assertNotNull($row);
        self::assertSame(12345, (int) $row['subtotal_minor']);
        self::assertSame(0, (int) $row['delivery_minor']);
        self::assertSame(12345, (int) $row['total_minor']);
        self::assertNull($row['fulfillment_method_id']);
        self::assertNull($row['fulfillment_type']);
        self::assertNull($row['fulfillment_name']);
    }
}
