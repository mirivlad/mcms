<?php

declare(strict_types=1);

namespace MCMS\Modules\Orders\Services;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use MCMS\Modules\Cart\Api\CartInterface;
use MCMS\Modules\Orders\Api\OrdersInterface;
use MCMS\Modules\Stock\Api\StockInterface;
use RuntimeException;
use Throwable;

final class Orders implements OrdersInterface
{
    private const STATUSES = ['new', 'processing', 'ready', 'completed', 'cancelled'];
    private const TERMINAL = ['completed', 'cancelled'];

    public function __construct(
        private readonly BaseConnection $db,
        private readonly CartInterface $cart,
        private readonly StockInterface $stock,
    ) {
    }

    public function createFromCart(array $customer): array
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
                'subtotal_minor' => (int) $cart['subtotal_minor'],
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
        foreach (['id', 'subtotal_minor'] as $field) {
            $row[$field] = (int) $row[$field];
        }
        $row['customer_user_id'] = $row['customer_user_id'] === null ? null : (int) $row['customer_user_id'];
        $row['manager_user_id'] = $row['manager_user_id'] === null ? null : (int) $row['manager_user_id'];
        $row['subtotal_display'] = $this->money((int) $row['subtotal_minor']);
        $row['status_label'] = lang('Orders.status.' . $row['status']);

        return $row;
    }

    private function money(int $minor): string
    {
        return number_format(intdiv($minor, 100), 0, ',', ' ')
            . ',' . str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT) . ' ₽';
    }
}
