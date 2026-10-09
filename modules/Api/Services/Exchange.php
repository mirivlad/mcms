<?php

declare(strict_types=1);

namespace MCMS\Modules\Api\Services;

use CodeIgniter\Database\BaseConnection;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Modules\Api\Api\ApiException;
use MCMS\Modules\Stock\Api\StockConflict;
use Throwable;

final class Exchange
{
    public function __construct(private readonly BaseConnection $db, private readonly ModuleServiceRegistry $services, private readonly Keys $keys)
    {
    }

    public function stockPage(int $afterId, int $limit, ?int $untilId): array
    {
        $page = $this->services->get('catalogue.exchange')->variantPage($afterId, $limit, $untilId);
        $levels = $this->services->get('stock.inventory')->levels(array_column($page['items'], 'id'));
        $page['items'] = array_map(fn (array $row): array => $this->stockData($row, $levels[$row['id']]), $page['items']);
        return $page;
    }

    public function stockOne(int $id): array
    {
        $variant = $this->services->get('catalogue.repository')->findVariant($id);
        if ($variant === null) {
            throw new ApiException(404, 'not_found', 'Variant not found.');
        }
        return $this->stockData($variant, $this->services->get('stock.inventory')->level($id));
    }

    public function updateStock(int $id, array $body, array $key, string $requestKey): array
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/D', $requestKey) !== 1) {
            throw new ApiException(422, 'invalid_request', 'An Idempotency-Key of 1 to 128 ASCII letters, numbers, dots, colons, underscores or hyphens is required.');
        }
        if (array_diff(array_keys($body), ['on_hand', 'expected_on_hand', 'note']) !== []
            || ! is_int($body['on_hand'] ?? null) || ! is_int($body['expected_on_hand'] ?? null)
            || $body['on_hand'] < 0 || $body['on_hand'] > 2_000_000_000
            || $body['expected_on_hand'] < 0 || $body['expected_on_hand'] > 2_000_000_000
            || (isset($body['note']) && (! is_string($body['note']) || mb_strlen($body['note']) > 150))) {
            throw new ApiException(422, 'invalid_request', 'Use integer on_hand/expected_on_hand between 0 and 2000000000 and an optional note up to 150 characters.');
        }
        // Canonical semantic fields make JSON whitespace/key order irrelevant.
        $note = trim($body['note'] ?? '');
        $hash = hash('sha256', json_encode([$id, $body['on_hand'], $body['expected_on_hand'], $note], JSON_THROW_ON_ERROR));
        $this->db->transBegin();
        try {
            // Serialize retries per key. Stock separately locks the variant row,
            // so two different keys cannot both overwrite the same baseline.
            $this->db->query('UPDATE ' . $this->db->prefixTable('api_keys') . ' SET id = id WHERE id = ?', [$key['id']]);
            $this->keys->requireActive($key['id'], 'stock.write');
            $existing = $this->db->table('api_operations')->where('key_id', $key['id'])->where('request_key_hash', hash('sha256', $requestKey))->get()->getRowArray();
            if ($existing !== null) {
                if (! hash_equals($existing['request_hash'], $hash)) {
                    throw new ApiException(409, 'idempotency_conflict', 'This Idempotency-Key was already used for a different operation.');
                }
                $result = json_decode($existing['response_json'], true, 16, JSON_THROW_ON_ERROR);
            } else {
                if ($this->services->get('catalogue.repository')->findVariant($id) === null) {
                    throw new ApiException(404, 'not_found', 'Variant not found.');
                }
                $result = $this->services->get('stock.exchange')->compareAndSetOnHand(
                    $id, $body['expected_on_hand'], $body['on_hand'], $key['user_id'],
                    'api:key:' . $key['id'] . ' operation:' . $requestKey . ($note === '' ? '' : ' ' . $note)
                );
                $this->db->table('api_operations')->insert([
                    'key_id' => $key['id'], 'request_key_hash' => hash('sha256', $requestKey), 'request_hash' => $hash,
                    'response_json' => json_encode($result, JSON_THROW_ON_ERROR), 'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
            if (! $this->db->transStatus()) {
                throw new \RuntimeException('Cannot commit the stock exchange.');
            }
            $this->db->transCommit();
            return $result;
        } catch (StockConflict $e) {
            $this->db->transRollback();
            throw new ApiException(409, 'stock_conflict', $e->getMessage(), ['current' => $e->current]);
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function ordersPage(int $afterId, int $limit, ?int $untilId, string $updatedSince, string $status): array
    {
        $page = $this->services->get('orders.exchange')->exportPage($afterId, $limit, $untilId, $updatedSince, $status);
        $page['items'] = array_map($this->orderData(...), $page['items']);
        return $page;
    }

    public function orderOne(int $id): array
    {
        $order = $this->services->get('orders.orders')->find($id);
        if ($order === null) {
            throw new ApiException(404, 'not_found', 'Order not found.');
        }
        return $this->orderData($order);
    }

    public function deliveryMethods(): array
    {
        if (! $this->services->has('delivery.methods')) {
            throw new ApiException(503, 'module_unavailable', 'Enable Delivery to read the current delivery methods. Order delivery snapshots remain available.');
        }
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'], 'name' => $row['name'], 'type' => $row['type'],
            'price_minor' => (int) $row['price_minor'], 'currency' => 'RUB',
            'enabled' => (bool) $row['enabled'], 'position' => (int) $row['position'],
        ], $this->services->get('delivery.methods')->all());
    }

    private function stockData(array $variant, array $level): array
    {
        return [
            'variant_id' => (int) $variant['id'], 'product_id' => (int) $variant['product_id'],
            'sku' => $variant['sku'], 'label' => $variant['label'], 'product_title' => $variant['product_title'],
            'product_status' => $variant['product_status'],
            'on_hand' => $level['on_hand'], 'reserved' => $level['reserved'], 'available' => $level['available'],
            'updated_at' => $level['updated_at'],
        ];
    }

    private function orderData(array $order): array
    {
        return [
            'id' => $order['id'], 'status' => $order['status'],
            'customer' => ['name' => $order['customer_name'], 'phone' => $order['phone'], 'email' => $order['email'], 'comment' => $order['comment']],
            'subtotal_minor' => $order['subtotal_minor'], 'delivery_minor' => $order['delivery_minor'],
            'total_minor' => $order['total_minor'], 'currency' => $order['currency'],
            'fulfillment' => ['method_id' => $order['fulfillment_method_id'], 'type' => $order['fulfillment_type'], 'name' => $order['fulfillment_name'], 'price_minor' => $order['delivery_minor']],
            'created_at' => $order['created_at'], 'updated_at' => $order['updated_at'],
            'completed_at' => $order['completed_at'], 'cancelled_at' => $order['cancelled_at'],
            'items' => array_map(static fn (array $item): array => array_intersect_key($item, array_flip([
                'variant_id', 'product_id', 'product_title', 'sku', 'variant_label', 'quantity', 'unit_price_minor', 'subtotal_minor', 'currency',
            ])), $order['items']),
        ];
    }
}
