<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Modules\Api\Api\ApiException;
use MCMS\Modules\Api\Services\Exchange;
use MCMS\Modules\Api\Services\Keys;

final class ApiModuleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\\Settings', 'CodeIgniter\\Shield'];
    private ModuleManager $manager;
    private ModuleServiceRegistry $services;
    private Keys $keys;
    private Exchange $exchange;
    private int $userId;
    private int $variantId;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();
        service('language')->setLocale('en');
        $this->services = new ModuleServiceRegistry();
        $this->manager = new ModuleManager($this->db, new AdminRegistry(), $this->services, ROOTPATH . 'modules');
        $this->manager->enable('api');
        $this->manager->enable('delivery');
        $this->manager->bootEnabled();
        $this->keys = new Keys($this->db);
        $this->exchange = new Exchange($this->db, $this->services, $this->keys);
        $users = model(UserModel::class);
        $this->userId = (int) $users->insert(new User(['username' => 'apiowner', 'email' => 'api@example.test', 'password' => 'Api-Owner-2026!', 'active' => true]));
        $users->findById($this->userId)->syncGroups('superadmin');
        $this->db->table('catalogue_products')->insert([
            'title' => 'Stock test', 'slug' => 'stock-api-test', 'category' => '', 'category_id' => null,
            'summary' => '', 'body' => '', 'status' => 'draft',
            'image_set_id' => bin2hex(random_bytes(16)), 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('catalogue_variants')->insert([
            'product_id' => (int) $this->db->insertID(), 'sku' => 'API-STOCK-1', 'label' => 'Small', 'price_minor' => 12345, 'currency' => 'RUB', 'position' => 0,
        ]);
        $this->variantId = (int) $this->db->insertID();
    }

    protected function tearDown(): void
    {
        foreach (['Api', 'Delivery', 'Orders', 'Stock', 'Cart', 'Catalogue', 'ProductImages'] as $module) {
            $this->migrations->setNamespace('MCMS\\Modules\\' . $module)->regress(0, 'tests');
        }
        $this->resetServices();
        parent::tearDown();
    }

    private function key(array $scopes = ['stock.read', 'stock.write', 'orders.read', 'delivery.read']): array
    {
        $key = $this->keys->create('1C connection', $scopes, $this->userId);
        return [...$key, 'user_id' => $this->userId];
    }

    private function error(callable $operation, int $status, string $code): void
    {
        try {
            $operation();
            self::fail('Expected API failure');
        } catch (ApiException $e) {
            self::assertSame($status, $e->status);
            self::assertSame($code, $e->errorCode);
        }
    }

    public function testKeysAreHashedScopedRevocableAndBoundToCurrentOwnerPermissions(): void
    {
        $key = $this->key(['stock.read']);
        $row = $this->db->table('api_keys')->get()->getRowArray();
        self::assertSame(hash('sha256', $key['token']), $row['token_hash']);
        self::assertStringNotContainsString($key['token'], json_encode($this->keys->all()));
        self::assertSame($key['id'], $this->keys->authenticate('Bearer ' . $key['token'], 'stock.read')['id']);
        $this->error(fn () => $this->keys->authenticate('Bearer ' . $key['token'], 'stock.write'), 403, 'forbidden');
        $this->error(fn () => $this->keys->authenticate('', 'stock.read'), 401, 'unauthorized');
        $owner = model(UserModel::class)->findById($this->userId);
        $owner->syncGroups('user');
        $this->error(fn () => $this->keys->authenticate('Bearer ' . $key['token'], 'stock.read'), 403, 'forbidden');
        $owner->syncGroups('superadmin');
        $this->keys->revoke($key['id']);
        $this->error(fn () => $this->keys->authenticate('Bearer ' . $key['token'], 'stock.read'), 401, 'unauthorized');
    }

    public function testExpiredDisabledAndDeletedOwnersCannotUseKeys(): void
    {
        $key = $this->key();
        $this->db->table('api_keys')->where('id', $key['id'])->update(['expires_at' => '2000-01-01 00:00:00']);
        $this->error(fn () => $this->keys->authenticate('Bearer ' . $key['token'], 'stock.read'), 401, 'unauthorized');
        $this->db->table('api_keys')->where('id', $key['id'])->update(['expires_at' => null]);
        $this->db->table('users')->where('id', $this->userId)->update(['active' => 0]);
        $this->error(fn () => $this->keys->authenticate('Bearer ' . $key['token'], 'stock.read'), 401, 'unauthorized');
        model(UserModel::class)->delete($this->userId);
        $this->error(fn () => $this->keys->authenticate('Bearer ' . $key['token'], 'stock.read'), 401, 'unauthorized');
    }

    public function testUpdatePreservesReservationsChecksBaselineAndRetriesCannotResurrectConsumedStock(): void
    {
        $key = $this->key();
        $stock = $this->services->get('stock.inventory');
        $stock->setOnHand($this->variantId, 10, $this->userId);
        $stock->reserve('test:order', $this->variantId, 3);
        $body = ['on_hand' => 12, 'expected_on_hand' => 10, 'note' => '1C receipt'];
        $result = $this->exchange->updateStock($this->variantId, $body, $key, 'operation-1');
        self::assertSame([12, 3, 9], [$result['on_hand'], $result['reserved'], $result['available']]);
        $count = $this->db->table('stock_movements')->countAllResults();
        $stock->consume('test:order');
        $replay = $this->exchange->updateStock($this->variantId, array_reverse($body, true), $key, 'operation-1');
        self::assertSame($result, $replay);
        self::assertSame(9, $stock->level($this->variantId)['on_hand']);
        self::assertSame($count + 1, $this->db->table('stock_movements')->countAllResults());
        $this->error(fn () => $this->exchange->updateStock($this->variantId, ['on_hand' => 14, 'expected_on_hand' => 9], $key, 'operation-1'), 409, 'idempotency_conflict');
        $this->error(fn () => $this->exchange->updateStock($this->variantId, $body, $key, 'operation-2'), 409, 'stock_conflict');
        $stock->reserve('test:order2', $this->variantId, 3);
        $this->error(fn () => $this->exchange->updateStock($this->variantId, ['on_hand' => 2, 'expected_on_hand' => 9], $key, 'operation-3'), 409, 'stock_conflict');
        self::assertSame(9, $stock->level($this->variantId)['on_hand']);
        self::assertSame(1, $this->db->table('api_operations')->countAllResults());
        $this->keys->revoke($key['id']);
        $this->error(fn () => $this->exchange->updateStock($this->variantId, $body, $key, 'operation-1'), 401, 'unauthorized');
    }

    public function testStockFeedIncludesDraftsAndKeepsAnIdCursorAcrossCatalogueEdits(): void
    {
        $page = $this->exchange->stockPage(0, 1, null);
        self::assertSame('API-STOCK-1', $page['items'][0]['sku']);
        self::assertSame('draft', $page['items'][0]['product_status']);
        self::assertSame(0, $page['items'][0]['available']);
        self::assertSame($this->variantId, $page['until_id']);
        self::assertFalse($page['has_more']);
        self::assertSame([], $this->exchange->stockPage($page['next_cursor'], 1, $page['until_id'])['items']);
        $this->error(fn () => $this->exchange->stockOne(999999), 404, 'not_found');
    }

    private function order(string $date, array $changes = []): int
    {
        $this->db->table('orders')->insert([
            'status' => 'new', 'customer_name' => 'Snapshot customer', 'phone' => '+70000000000', 'email' => null, 'comment' => 'Door 4',
            'subtotal_minor' => 12345, 'delivery_minor' => 99, 'total_minor' => 12444, 'currency' => 'RUB',
            'reservation_key' => bin2hex(random_bytes(16)), 'created_at' => $date, 'updated_at' => $date,
            'fulfillment_method_id' => 999, 'fulfillment_type' => 'delivery', 'fulfillment_name' => 'Historical delivery', ...$changes,
        ]);
        $id = (int) $this->db->insertID();
        $this->db->table('order_items')->insert([
            'order_id' => $id, 'product_id' => null, 'variant_id' => null, 'product_title' => 'Removed product', 'product_slug' => 'old',
            'sku' => 'HISTORICAL-SKU', 'variant_label' => 'Old size', 'unit_price_minor' => 12345, 'quantity' => 1, 'subtotal_minor' => 12345, 'currency' => 'RUB',
        ]);
        return $id;
    }

    public function testOrderFeedHasDeterministicPaginationInclusiveChangesAndImmutableSnapshots(): void
    {
        $first = $this->order('2026-10-09 12:00:00');
        $second = $this->order('2026-10-09 12:00:00');
        $page = $this->exchange->ordersPage(0, 1, null, '2026-10-09 12:00:00', '');
        self::assertTrue($page['has_more']);
        self::assertSame($first, $page['next_cursor']);
        $third = $this->order('2026-10-09 12:00:01');
        $next = $this->exchange->ordersPage($first, 1, $page['until_id'], '2026-10-09 12:00:00', '');
        self::assertSame($second, $next['items'][0]['id']);
        self::assertFalse($next['has_more']);
        self::assertSame('HISTORICAL-SKU', $page['items'][0]['items'][0]['sku']);
        self::assertSame('Historical delivery', $page['items'][0]['fulfillment']['name']);
        self::assertSame(99, $page['items'][0]['fulfillment']['price_minor']);
        self::assertArrayNotHasKey('reservation_key', $page['items'][0]);
        self::assertArrayNotHasKey('customer_user_id', $page['items'][0]);
        $this->db->table('orders')->where('id', $first)->update(['status' => 'cancelled', 'updated_at' => '2026-10-09 12:00:01']);
        $changed = $this->exchange->ordersPage(0, 100, null, '2026-10-09 12:00:01', '');
        self::assertSame([$first, $third], array_column($changed['items'], 'id'));
        $this->error(fn () => $this->exchange->orderOne(999999), 404, 'not_found');
    }

    public function testFailedReceiptStorageRollsBackTheStockCorrectionAndAudit(): void
    {
        $key = $this->key();
        $stock = $this->services->get('stock.inventory');
        $stock->setOnHand($this->variantId, 10, $this->userId);
        $movements = $this->db->table('stock_movements')->countAllResults();
        $this->db->query("CREATE TRIGGER fail_api_receipt BEFORE INSERT ON " . $this->db->prefixTable('api_operations') . " BEGIN SELECT RAISE(ABORT, 'receipt storage failed'); END");
        try {
            $this->exchange->updateStock($this->variantId, ['on_hand' => 12, 'expected_on_hand' => 10], $key, 'failed-receipt');
            self::fail('Storage failure must abort the exchange');
        } catch (\RuntimeException $e) {
            self::assertNotSame('', $e->getMessage());
        } finally {
            $this->db->query('DROP TRIGGER fail_api_receipt');
            $this->db->resetTransStatus();
        }
        self::assertSame(10, $stock->level($this->variantId)['on_hand']);
        self::assertSame($movements, $this->db->table('stock_movements')->countAllResults());
        self::assertSame(0, $this->db->table('api_operations')->countAllResults());
    }

    public function testKeyCreationAndStockWritesRejectInvalidContracts(): void
    {
        $this->error(fn () => $this->keys->create('Invalid', ['unknown'], $this->userId), 422, 'invalid_request');
        $this->error(fn () => $this->keys->create('Invalid', [['stock.read']], $this->userId), 422, 'invalid_request');
        $this->error(fn () => $this->keys->create('Invalid', ['stock.read'], $this->userId, '2026-02-30'), 422, 'invalid_request');
        $key = $this->key();
        foreach ([['on_hand' => true, 'expected_on_hand' => 0], ['on_hand' => '1', 'expected_on_hand' => 0],
            ['on_hand' => 2_000_000_001, 'expected_on_hand' => 0], ['on_hand' => 1, 'expected_on_hand' => 0, 'extra' => 1]] as $body) {
            $this->error(fn () => $this->exchange->updateStock($this->variantId, $body, $key, 'invalid'), 422, 'invalid_request');
        }
        $this->error(fn () => $this->exchange->updateStock($this->variantId, ['on_hand' => 1, 'expected_on_hand' => 0], $key, ''), 422, 'invalid_request');
        self::assertSame(0, $this->db->table('api_operations')->countAllResults());
    }

    public function testModuleDisablePreservesKeysAndStockWhileRemovingServices(): void
    {
        $key = $this->key();
        $this->manager->disable('api');
        $fresh = new ModuleServiceRegistry();
        $manager = new ModuleManager($this->db, new AdminRegistry(), $fresh, ROOTPATH . 'modules');
        $manager->bootEnabled();
        self::assertFalse($fresh->has('api.keys'));
        self::assertSame($key['id'], $this->keys->authenticate('Bearer ' . $key['token'], 'stock.read')['id']);
        $manager->enable('api');
        self::assertSame(1, $this->db->table('api_keys')->countAllResults());
    }
}
