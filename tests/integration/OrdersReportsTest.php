<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Core\Navigation\NavigableResourceRegistry;
use MCMS\Modules\Orders\Api\ReportsInterface;

final class OrdersReportsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\\Settings'];
    private ModuleServiceRegistry $services;
    private ReportsInterface $reports;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();
        service('language')->setLocale('en');
        $this->services = new ModuleServiceRegistry();
        $manager = new ModuleManager($this->db, new AdminRegistry(), $this->services, ROOTPATH . 'modules', new NavigableResourceRegistry());
        $manager->enable('orders');
        $manager->bootEnabled();
        $this->reports = $this->services->get('orders.reports');
    }

    protected function tearDown(): void
    {
        foreach (['Orders', 'Stock', 'Cart', 'Catalogue', 'ProductImages'] as $module) {
            $this->migrations->setNamespace('MCMS\\Modules\\' . $module)->regress(0, 'tests');
        }
        $this->resetServices();
        parent::tearDown();
    }

    private function order(array $fields = []): int
    {
        $this->db->table('orders')->insert([
            'status' => 'completed', 'customer_name' => 'Historical customer', 'phone' => '+70000000000',
            'email' => null, 'comment' => null, 'subtotal_minor' => 10001, 'delivery_minor' => 199,
            'total_minor' => 10200, 'currency' => 'RUB', 'reservation_key' => bin2hex(random_bytes(16)),
            'created_at' => '2026-10-08 12:00:00', 'updated_at' => '2026-10-08 12:00:00', ...$fields,
        ]);
        return (int) $this->db->insertID();
    }

    private function item(int $orderId, array $fields = []): void
    {
        $this->db->table('order_items')->insert([
            'order_id' => $orderId, 'product_id' => null, 'variant_id' => null,
            'product_title' => 'Historical product', 'product_slug' => 'old-slug',
            'sku' => 'HISTORICAL-SKU', 'variant_label' => 'Old label', 'unit_price_minor' => 10001,
            'quantity' => 1, 'subtotal_minor' => 10001, 'currency' => 'RUB', ...$fields,
        ]);
    }

    private function csv(string $format, string $from = '', string $to = '', string $status = ''): array
    {
        $stream = tmpfile();
        try {
            $this->reports->writeCsv($stream, $format, $from, $to, $status);
            rewind($stream);
            self::assertSame("\xEF\xBB\xBF", fread($stream, 3));
            $header = fgetcsv($stream, null, ';', '"', '');
            $rows = [];
            while (($row = fgetcsv($stream, null, ';', '"', '')) !== false) {
                $rows[] = array_combine($header, $row);
            }
            return $rows;
        } finally {
            fclose($stream);
        }
    }

    public function testInclusiveDatesAndStatusCurrencyGroupsUseOrderTotalsWithoutLineDuplication(): void
    {
        $first = $this->order(['created_at' => '2026-10-08 00:00:00']);
        $this->item($first);
        $this->item($first); // The report aggregates orders, never an items join.
        $this->order(['created_at' => '2026-10-08 23:59:59']);
        $this->order(['created_at' => '2026-10-07 23:59:59']);
        $this->order(['created_at' => '2026-10-09 00:00:00']);
        $this->order(['status' => 'cancelled']);
        $this->order(['currency' => 'USD']);
        $rows = $this->reports->summary('2026-10-08', '2026-10-08');
        self::assertCount(3, $rows);
        $rub = array_values(array_filter($rows, static fn (array $row): bool => $row['currency'] === 'RUB' && $row['status'] === 'completed'))[0];
        self::assertSame(2, $rub['order_count']);
        self::assertSame(20002, $rub['subtotal_minor']);
        self::assertSame(398, $rub['delivery_minor']);
        self::assertSame(20400, $rub['total_minor']);
        self::assertCount(1, $this->reports->summary('2026-10-08', '2026-10-08', 'cancelled'));
        self::assertSame([], $this->reports->summary('2027-01-01', '2027-01-31'));
        self::assertSame(6, array_sum(array_column($this->reports->summary(), 'order_count')));
    }

    public function testCsvPreservesSnapshotsAndEscapesSpreadsheetTextAndMultilineCells(): void
    {
        $id = $this->order([
            'customer_name' => '  =1+1', 'comment' => "Line one; \"quoted\"\r\nLine two",
            'fulfillment_name' => '@old pickup', 'fulfillment_type' => 'pickup',
            'fulfillment_method_id' => 123,
        ]);
        $this->item($id, ['product_title' => '=old product', 'sku' => "\t=old-sku", 'variant_label' => '-old label']);
        $before = $this->db->table('orders')->get()->getResultArray();
        $rows = $this->csv('orders');
        self::assertSame("'  =1+1", $rows[0]['customer_name']);
        self::assertSame("'+70000000000", $rows[0]['phone']);
        self::assertSame("'@old pickup", $rows[0]['fulfillment_name']);
        self::assertSame("Line one; \"quoted\"\r\nLine two", $rows[0]['comment']);
        self::assertSame('10001', $rows[0]['subtotal_minor']);
        self::assertSame('199', $rows[0]['delivery_minor']);
        self::assertSame('10200', $rows[0]['total_minor']);
        $items = $this->csv('items');
        self::assertSame("'=old product", $items[0]['product_title']);
        self::assertSame("'\t=old-sku", $items[0]['sku']);
        self::assertSame("'-old label", $items[0]['variant_label']);
        self::assertSame('', $items[0]['product_id']);
        self::assertSame('10001', $items[0]['unit_price_minor']);
        self::assertSame($before, $this->db->table('orders')->get()->getResultArray());
        self::assertSame(0, $this->db->table('stock_movements')->countAllResults());
    }

    public function testBothExportsIncludeAllBatchesAndRespectIdenticalFilters(): void
    {
        for ($index = 0; $index < 502; $index++) {
            $id = $this->order();
            $this->item($id);
        }
        $excluded = $this->order(['status' => 'cancelled']);
        $this->item($excluded);
        foreach (['orders', 'items'] as $format) {
            $rows = $this->csv($format, '2026-10-08', '2026-10-08', 'completed');
            self::assertCount(502, $rows);
            self::assertCount(502, array_unique(array_column($rows, 'order_id')));
            self::assertSame('502', $rows[501]['order_id']);
            self::assertSame([], $this->csv($format, '2027-01-01', '2027-01-31'));
        }
    }

    public function testInvalidDatesStatusesAndFormatsNeverProduceAnUnfilteredExport(): void
    {
        foreach ([['2026-02-30', '', ''], ['2026-10-09', '2026-10-08', ''], ["2026-10-08\0", '', ''], ['', 'yesterday', ''], ['', '', 'unknown']] as $filters) {
            try {
                $this->reports->summary(...$filters);
                self::fail('Invalid report filters accepted');
            } catch (InvalidArgumentException) {
                $stream = tmpfile();
                try {
                    $this->reports->writeCsv($stream, 'orders', ...$filters);
                    self::fail('Invalid export filters accepted');
                } catch (InvalidArgumentException) {
                    self::assertSame(0, ftell($stream));
                } finally {
                    fclose($stream);
                }
            }
        }
        $stream = tmpfile();
        try {
            $this->reports->writeCsv($stream, 'unknown');
            self::fail('Unknown export format accepted');
        } catch (InvalidArgumentException) {
            self::assertSame(0, ftell($stream));
        } finally {
            fclose($stream);
        }
    }

    public function testReportViewDoesNotGrantExportAndInvalidFiltersDisableDownloadLinks(): void
    {
        Services::injectMock('mcmsModuleServices', $this->services);
        $auth = $this->createMock(\CodeIgniter\Shield\Auth::class);
        $user = $this->createMock(\CodeIgniter\Shield\Entities\User::class);
        $user->method('can')->willReturnCallback(static fn (string $permission): bool => $permission === 'orders.view');
        $auth->method('user')->willReturn($user);
        $auth->method('setAuthenticator')->willReturnSelf();
        Services::injectMock('auth', $auth);
        $request = service('request');
        $request->setGlobal('get', ['from' => '', 'to' => '', 'status' => '']);
        $controller = new \MCMS\Modules\Orders\Controllers\Reports();
        $controller->initController($request, service('response'), service('logger'));
        $html = $controller->index();
        self::assertStringContainsString('Order report', $html);
        self::assertStringNotContainsString('/admin/orders/export', $html);
        self::assertStringContainsString('private', service('response')->getHeaderLine('Cache-Control'));
        self::assertStringContainsString('no-store', service('response')->getHeaderLine('Cache-Control'));
        $denied = $controller->export();
        self::assertSame(403, $denied->getStatusCode());
        self::assertFalse($denied->hasHeader('Content-Disposition'));

        $request->setGlobal('get', ['from' => '2026-02-30', 'to' => '', 'status' => '']);
        $html = $controller->index();
        self::assertStringContainsString('Enter valid dates', $html);
        self::assertSame(422, service('response')->getStatusCode());
        self::assertStringNotContainsString('/admin/orders/export', $html);
    }
}
