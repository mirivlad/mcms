<?php

declare(strict_types=1);

namespace MCMS\Modules\Orders\Services;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use MCMS\Modules\Cart\Api\CartInterface;
use MCMS\Modules\Orders\Api\OrdersInterface;
use MCMS\Modules\Orders\Api\OrderFeedInterface;
use MCMS\Modules\Stock\Api\StockInterface;
use RuntimeException;
use Throwable;

final class Orders implements OrdersInterface, OrderFeedInterface
{
    private const STATUSES = ['new', 'processing', 'ready', 'completed', 'cancelled'];
    private const TERMINAL = ['completed', 'cancelled'];

    public function __construct(
        private readonly BaseConnection $db,
        private readonly CartInterface $cart,
        private readonly StockInterface $stock,
    ) {
    }

    public function createFromCart(array $customer, ?array $fulfillment = null): array
    {
        $cart = $this->cart->snapshot();
        if ($cart['items'] === []) {
            throw new InvalidArgumentException(lang('Orders.emptyCart'));
        }
        foreach ($cart['items'] as $item) {
            if (! empty($item['stock_adjusted'])) {
                throw new RuntimeException(lang('Orders.cartChanged'));
            }
        }

        $name = mb_substr(trim((string) ($customer['name'] ?? '')), 0, 190);
        $phone = mb_substr(trim((string) ($customer['phone'] ?? '')), 0, 64);
        $email = mb_substr(trim((string) ($customer['email'] ?? '')), 0, 254);
        $comment = mb_substr(trim((string) ($customer['comment'] ?? '')), 0, 5000);

        if ($name === '') {
            throw new InvalidArgumentException(lang('Orders.nameRequired'));
        }
        if ($phone === '') {
            throw new InvalidArgumentException(lang('Orders.phoneRequired'));
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(lang('Orders.emailInvalid'));
        }

        $currency = (string) ($cart['items'][0]['currency'] ?? 'RUB');
        foreach ($cart['items'] as $item) {
            if ((string) $item['currency'] !== $currency) {
                throw new RuntimeException(lang('Orders.currencyMismatch'));
            }
        }

        $fulfillment = $this->fulfillmentSnapshot($fulfillment);
        $deliveryMinor = $fulfillment['price_minor'];
        $totalMinor = (int) $cart['subtotal_minor'] + $deliveryMinor;

        $this->db->transBegin();
        $orderId = 0;
        $reservationKey = '';
        try {
            $now = date('Y-m-d H:i:s');
            $this->db->table('orders')->insert([
                'status' => 'new',
                'customer_name' => $name,
                'phone' => $phone,
                'email' => $email === '' ? null : $email,
                'comment' => $comment === '' ? null : $comment,
                'fulfillment_method_id' => $fulfillment['id'],
                'fulfillment_type' => $fulfillment['type'],
                'fulfillment_name' => $fulfillment['name'],
                'subtotal_minor' => (int) $cart['subtotal_minor'],
                'delivery_minor' => $deliveryMinor,
                'total_minor' => $totalMinor,
                'currency' => $currency,
                'reservation_key' => 'pending:' . bin2hex(random_bytes(16)),
                'customer_user_id' => auth()->id() === null ? null : (int) auth()->id(),
                'manager_user_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'completed_at' => null,
                'cancelled_at' => null,
            ]);
            $orderId = (int) $this->db->insertID();
            if ($orderId < 1) {
                throw new RuntimeException(lang('Orders.createFailed'));
            }

            $reservationKey = 'order:' . $orderId;
            $this->db->table('orders')->where('id', $orderId)->update(['reservation_key' => $reservationKey]);

            foreach ($cart['items'] as $item) {
                $variantId = (int) $item['variant_id'];
                $quantity = (int) $item['quantity'];

                // This is the only point where cart contents turn into stock reservations.
                $this->stock->reserve($reservationKey, $variantId, $quantity);
                // Keep the Catalogue variant alive while this order owns an active reservation.
                $this->db->table('order_variant_locks')->insert([
                    'order_id' => $orderId,
                    'variant_id' => $variantId,
                ]);

                $this->db->table('order_items')->insert([
                    'order_id' => $orderId,
                    'variant_id' => $variantId,
                    'product_id' => (int) $item['product_id'],
                    'product_title' => (string) $item['product_title'],
                    'product_slug' => (string) $item['product_slug'],
                    'sku' => (string) $item['sku'],
                    'variant_label' => (string) $item['label'],
                    'unit_price_minor' => (int) $item['unit_price_minor'],
                    'quantity' => $quantity,
                    'subtotal_minor' => (int) $item['subtotal_minor'],
                    'currency' => (string) $item['currency'],
                ]);
            }

            $this->db->table('order_status_history')->insert([
                'order_id' => $orderId,
                'from_status' => null,
                'to_status' => 'new',
                'user_id' => auth()->id() === null ? null : (int) auth()->id(),
                'note' => '',
                'created_at' => $now,
            ]);

            if (! $this->db->transStatus()) {
                throw new RuntimeException(lang('Orders.createFailed'));
            }
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            if ($reservationKey !== '') {
                try {
                    $this->stock->release($reservationKey);
                } catch (Throwable) {
                }
            }
            throw $e;
        }

        $this->cart->clear();

        return $this->find($orderId) ?? throw new RuntimeException(lang('Orders.createFailed'));
    }

    public function list(string $query = '', string $status = '', int $limit = 50, int $offset = 0): array
    {
        $builder = $this->builder($query, $status)
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit(max(1, min(200, $limit)), max(0, $offset));

        return array_map(fn (array $row): array => $this->presentOrder($row), $builder->get()->getResultArray());
    }

    public function count(string $query = '', string $status = ''): int
    {
        return $this->builder($query, $status)->countAllResults();
    }

    public function find(int $id): ?array
    {
        $row = $this->db->table('orders')->where('id', $id)->get()->getRowArray();
        if ($row === null) {
            return null;
        }

        $order = $this->presentOrder($row);
        $order['items'] = array_map(function (array $item): array {
            $item['id'] = (int) $item['id'];
            $item['order_id'] = (int) $item['order_id'];
            $item['variant_id'] = $item['variant_id'] === null ? null : (int) $item['variant_id'];
            $item['product_id'] = $item['product_id'] === null ? null : (int) $item['product_id'];
            $item['unit_price_minor'] = (int) $item['unit_price_minor'];
            $item['quantity'] = (int) $item['quantity'];
            $item['subtotal_minor'] = (int) $item['subtotal_minor'];
            $item['unit_price_display'] = $this->money($item['unit_price_minor']);
            $item['subtotal_display'] = $this->money($item['subtotal_minor']);

            return $item;
        }, $this->db->table('order_items')->where('order_id', $id)->orderBy('id')->get()->getResultArray());

        $order['history'] = array_map(static function (array $entry): array {
            $entry['id'] = (int) $entry['id'];
            $entry['order_id'] = (int) $entry['order_id'];
            $entry['user_id'] = $entry['user_id'] === null ? null : (int) $entry['user_id'];

            return $entry;
        }, $this->db->table('order_status_history')->where('order_id', $id)->orderBy('id')->get()->getResultArray());

        $order['allowed_statuses'] = $this->allowedStatuses((string) $row['status']);

        return $order;
    }

    public function exportPage(int $afterId = 0, int $limit = 100, ?int $untilId = null, string $updatedSince = '', string $status = ''): array
    {
        if ($afterId < 0 || $limit < 1 || $limit > 200 || ($untilId !== null && $untilId < $afterId)
            || ($status !== '' && ! in_array($status, self::STATUSES, true))
            || ($updatedSince !== '' && (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $updatedSince) !== 1
                || date('Y-m-d H:i:s', strtotime($updatedSince) ?: 0) !== $updatedSince))) {
            throw new InvalidArgumentException('Invalid order feed cursor, date, status or limit.');
        }
        $untilId ??= max($afterId, (int) ($this->db->table('orders')->selectMax('id')->get()->getRowArray()['id'] ?? 0));
        $builder = $this->builder('', $status)->select('id')->where('id >', $afterId)->where('id <=', $untilId)->orderBy('id', 'ASC');
        if ($updatedSince !== '') {
            $builder->where('updated_at >=', $updatedSince);
        }
        $rows = $builder->limit($limit + 1)->get()->getResultArray();
        $more = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);
        $items = [];
        foreach ($rows as $row) {
            $order = $this->find((int) $row['id']);
            if ($order !== null) {
                $items[] = $order;
            }
        }
        return ['items' => $items, 'next_cursor' => $rows === [] ? $afterId : (int) end($rows)['id'], 'until_id' => $untilId, 'has_more' => $more];
    }

    public function transition(int $id, string $status, int $userId, string $note = ''): array
    {
        $status = trim($status);
        if (! in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException(lang('Orders.invalidStatus'));
        }

        $row = $this->db->table('orders')->where('id', $id)->get()->getRowArray();
        if ($row === null) {
            throw new InvalidArgumentException(lang('Orders.notFound'));
        }

        $from = (string) $row['status'];
        if ($status === $from) {
            return $this->find($id) ?? throw new RuntimeException(lang('Orders.notFound'));
        }
        if (in_array($from, self::TERMINAL, true)) {
            throw new RuntimeException(lang('Orders.terminalStatus'));
        }
        if (! in_array($status, $this->allowedStatuses($from), true)) {
            throw new RuntimeException(lang('Orders.invalidTransition'));
        }

        $note = mb_substr(trim($note), 0, 255);
        $this->db->transBegin();
        try {
            $reservationKey = (string) $row['reservation_key'];
            if ($status === 'completed') {
                $this->stock->consume($reservationKey);
                $this->db->table('order_variant_locks')->where('order_id', $id)->delete();
            } elseif ($status === 'cancelled') {
                $this->stock->release($reservationKey);
                $this->db->table('order_variant_locks')->where('order_id', $id)->delete();
            }

            $now = date('Y-m-d H:i:s');
            $update = [
                'status' => $status,
                'manager_user_id' => $userId > 0 ? $userId : null,
                'updated_at' => $now,
            ];
            if ($status === 'completed') {
                $update['completed_at'] = $now;
            }
            if ($status === 'cancelled') {
                $update['cancelled_at'] = $now;
            }
            $this->db->table('orders')->where('id', $id)->update($update);
            $this->db->table('order_status_history')->insert([
                'order_id' => $id,
                'from_status' => $from,
                'to_status' => $status,
                'user_id' => $userId > 0 ? $userId : null,
                'note' => $note,
                'created_at' => $now,
            ]);

            if (! $this->db->transStatus()) {
                throw new RuntimeException(lang('Orders.saveFailed'));
            }
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        return $this->find($id) ?? throw new RuntimeException(lang('Orders.notFound'));
    }

    public function statuses(): array
    {
        return self::STATUSES;
    }

    private function allowedStatuses(string $from): array
    {
        if (in_array($from, self::TERMINAL, true)) {
            return [];
        }

        // Managers may move freely between active workflow stages. Terminal states
        // are one-way because they release or consume the stock reservation.
        return array_values(array_filter(
            self::STATUSES,
            static fn (string $status): bool => $status !== $from
        ));
    }

    private function builder(string $query, string $status): object
    {
        $builder = $this->db->table('orders');
        $query = trim($query);
        if ($query !== '') {
            $builder->groupStart()
                ->like('customer_name', $query)
                ->orLike('phone', $query)
                ->orLike('email', $query);
            if (ctype_digit($query)) {
                $builder->orWhere('id', (int) $query);
            }
            $builder->groupEnd();
        }
        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $builder->where('status', $status);
        }

        return $builder;
    }

    private function presentOrder(array $row): array
    {
        foreach (['id', 'subtotal_minor', 'delivery_minor', 'total_minor'] as $field) {
            $row[$field] = (int) ($row[$field] ?? 0);
        }
        if ($row['total_minor'] === 0 && $row['subtotal_minor'] > 0) {
            $row['total_minor'] = $row['subtotal_minor'] + $row['delivery_minor'];
        }
        $row['customer_user_id'] = $row['customer_user_id'] === null ? null : (int) $row['customer_user_id'];
        $row['manager_user_id'] = $row['manager_user_id'] === null ? null : (int) $row['manager_user_id'];
        $row['fulfillment_method_id'] = ($row['fulfillment_method_id'] ?? null) === null
            ? null
            : (int) $row['fulfillment_method_id'];
        $row['subtotal_display'] = $this->money($row['subtotal_minor']);
        $row['delivery_display'] = $this->money($row['delivery_minor']);
        $row['total_display'] = $this->money($row['total_minor']);
        $row['fulfillment_type_label'] = ($row['fulfillment_type'] ?? null) === null
            ? null
            : lang('Orders.fulfillmentType.' . $row['fulfillment_type']);
        $row['status_label'] = lang('Orders.status.' . $row['status']);

        return $row;
    }

    /**
     * @return array{id:?int,type:?string,name:?string,price_minor:int}
     */
    private function fulfillmentSnapshot(?array $fulfillment): array
    {
        if ($fulfillment === null) {
            return ['id' => null, 'type' => null, 'name' => null, 'price_minor' => 0];
        }

        $id = filter_var($fulfillment['id'] ?? null, FILTER_VALIDATE_INT);
        $type = trim((string) ($fulfillment['type'] ?? ''));
        $name = trim((string) ($fulfillment['name'] ?? ''));
        $priceMinor = filter_var($fulfillment['price_minor'] ?? null, FILTER_VALIDATE_INT);

        if ($id === false || $id < 1 || ! in_array($type, ['delivery', 'pickup'], true)
            || $name === '' || mb_strlen($name) > 190 || $priceMinor === false || $priceMinor < 0) {
            throw new InvalidArgumentException(lang('Orders.invalidFulfillment'));
        }

        return [
            'id' => $id,
            'type' => $type,
            'name' => $name,
            'price_minor' => $priceMinor,
        ];
    }

    private function money(int $minor): string
    {
        return number_format(intdiv($minor, 100), 0, ',', ' ')
            . ',' . str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT) . ' ₽';
    }
}
