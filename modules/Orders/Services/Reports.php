<?php

declare(strict_types=1);

namespace MCMS\Modules\Orders\Services;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use InvalidArgumentException;
use MCMS\Modules\Orders\Api\OrdersInterface;
use MCMS\Modules\Orders\Api\ReportsInterface;
use RuntimeException;
use Throwable;

final class Reports implements ReportsInterface
{
    private const ORDER_COLUMNS = [
        'order_id', 'status', 'created_at', 'customer_name', 'phone', 'email', 'comment',
        'fulfillment_method_id', 'fulfillment_type', 'fulfillment_name',
        'subtotal_minor', 'delivery_minor', 'total_minor', 'currency', 'completed_at', 'cancelled_at',
    ];
    private const ITEM_COLUMNS = [
        'line_id', 'order_id', 'status', 'created_at', 'product_id', 'variant_id',
        'product_title', 'sku', 'variant_label', 'quantity', 'unit_price_minor',
        'line_subtotal_minor', 'currency',
    ];
    private const INTEGER_COLUMNS = [
        'order_id', 'line_id', 'product_id', 'variant_id', 'fulfillment_method_id',
        'quantity', 'unit_price_minor', 'line_subtotal_minor', 'subtotal_minor', 'delivery_minor', 'total_minor',
    ];

    public function __construct(private readonly BaseConnection $db, private readonly OrdersInterface $orders)
    {
    }

    public function summary(string $from = '', string $to = '', string $status = ''): array
    {
        $this->validate($from, $to, $status);
        $rows = $this->builder($from, $to, $status)
            ->select('o.status, o.currency, COUNT(*) AS order_count, SUM(o.subtotal_minor) AS subtotal_minor, SUM(o.delivery_minor) AS delivery_minor, SUM(o.total_minor) AS total_minor', false)
            ->groupBy(['o.status', 'o.currency'])->orderBy('o.currency')->orderBy('o.status')
            ->get()->getResultArray();
        foreach ($rows as &$row) {
            foreach (['order_count', 'subtotal_minor', 'delivery_minor', 'total_minor'] as $field) {
                $row[$field] = (int) $row[$field];
            }
        }
        unset($row);

        return $rows;
    }

    public function writeCsv($stream, string $format, string $from = '', string $to = '', string $status = ''): void
    {
        $this->validate($from, $to, $status);
        if (! in_array($format, ['orders', 'items'], true)) {
            throw new InvalidArgumentException(lang('Orders.Reports.invalidFormat'));
        }
        $columns = $format === 'orders' ? self::ORDER_COLUMNS : self::ITEM_COLUMNS;
        if (fwrite($stream, "\xEF\xBB\xBF") === false) {
            throw new RuntimeException(lang('Orders.Reports.exportFailed'));
        }
        $this->csvRow($stream, $columns);

        // A read transaction keeps all batches in one database snapshot. No catalogue,
        // delivery, reservation or session state is changed by reporting.
        $this->db->transBegin();
        try {
            $lastId = 0;
            do {
                $builder = $this->builder($from, $to, $status);
                if ($format === 'orders') {
                    $fields = array_map(static fn (string $column): string => $column === 'order_id' ? 'o.id AS order_id' : 'o.' . $column, $columns);
                    $idField = 'o.id';
                } else {
                    $builder->join('order_items i', 'i.order_id = o.id');
                    $fields = [
                        'i.id AS line_id', 'o.id AS order_id', 'o.status', 'o.created_at',
                        'i.product_id', 'i.variant_id', 'i.product_title', 'i.sku', 'i.variant_label',
                        'i.quantity', 'i.unit_price_minor', 'i.subtotal_minor AS line_subtotal_minor', 'i.currency',
                    ];
                    $idField = 'i.id';
                }
                $rows = $builder->select($fields)->where($idField . ' >', $lastId)
                    ->orderBy($idField)->limit(500)->get()->getResultArray();
                foreach ($rows as $row) {
                    $cells = [];
                    foreach ($columns as $column) {
                        $value = $row[$column] ?? '';
                        $cells[] = $value !== '' && in_array($column, self::INTEGER_COLUMNS, true)
                            ? (int) $value : $this->safeText((string) $value);
                    }
                    $this->csvRow($stream, $cells);
                    $lastId = (int) $row[$format === 'orders' ? 'order_id' : 'line_id'];
                }
            } while (count($rows) === 500);
            if (! $this->db->transStatus()) {
                throw new RuntimeException(lang('Orders.Reports.exportFailed'));
            }
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    private function builder(string $from, string $to, string $status): BaseBuilder
    {
        $builder = $this->db->table('orders o');
        if ($from !== '') {
            $builder->where('o.created_at >=', $from . ' 00:00:00');
        }
        if ($to !== '') {
            $builder->where('o.created_at <=', $to . ' 23:59:59');
        }
        if ($status !== '') {
            $builder->where('o.status', $status);
        }

        return $builder;
    }

    private function validate(string $from, string $to, string $status): void
    {
        foreach ([$from, $to] as $date) {
            if ($date === '') {
                continue;
            }
            if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $date) !== 1) {
                throw new InvalidArgumentException(lang('Orders.Reports.invalidDates'));
            }
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if ($parsed === false || $parsed->format('Y-m-d') !== $date || substr($date, 0, 4) === '0000') {
                throw new InvalidArgumentException(lang('Orders.Reports.invalidDates'));
            }
        }
        if ($from !== '' && $to !== '' && $from > $to) {
            throw new InvalidArgumentException(lang('Orders.Reports.invalidDates'));
        }
        if ($status !== '' && ! in_array($status, $this->orders->statuses(), true)) {
            throw new InvalidArgumentException(lang('Orders.invalidStatus'));
        }
    }

    private function safeText(string $value): string
    {
        // Spreadsheet applications must treat customer text, phone numbers and SKUs
        // as text, even when a formula marker follows leading whitespace.
        return preg_match('/^(?:[\t\r\n]|[\x00-\x20]*[=+\-@])/', $value) === 1 ? "'" . $value : $value;
    }

    private function csvRow($stream, array $cells): void
    {
        if (fputcsv($stream, $cells, ';', '"', '', "\r\n") === false) {
            throw new RuntimeException(lang('Orders.Reports.exportFailed'));
        }
    }
}
