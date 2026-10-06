<?php

declare(strict_types=1);

namespace MCMS\Modules\Stock\Services;

use CodeIgniter\Database\BaseConnection;
use DateTimeInterface;
use InvalidArgumentException;
use MCMS\Modules\Catalogue\Api\CatalogueInterface;
use MCMS\Modules\Stock\Api\StockInterface;
use RuntimeException;
use Throwable;

final class Stock implements StockInterface
{
    private const MAX_QUANTITY = 2_000_000_000;

    public function __construct(
        private readonly BaseConnection $db,
        private readonly CatalogueInterface $catalogue,
    ) {
    }

    public function level(int $variantId): array
    {
        $levels = $this->levels([$variantId]);

        return $levels[$variantId] ?? $this->emptyLevel($variantId);
    }

    public function levels(array $variantIds): array
    {
        $this->purgeExpired();
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $variantIds),
            static fn (int $id): bool => $id > 0
        )));
        if ($ids === []) {
            return [];
        }

        $result = [];
        foreach ($ids as $id) {
            $result[$id] = $this->emptyLevel($id);
        }

        foreach ($this->db->table('stock_levels')->whereIn('variant_id', $ids)->get()->getResultArray() as $row) {
            $id = (int) $row['variant_id'];
            $onHand = (int) $row['on_hand'];
            $reserved = (int) $row['reserved'];
            $result[$id] = [
                'variant_id' => $id,
                'on_hand' => $onHand,
                'reserved' => $reserved,
                'available' => max(0, $onHand - $reserved),
                'updated_at' => $row['updated_at'] ?: null,
            ];
        }

        return $result;
    }

    public function list(string $query = '', int $limit = 50, int $offset = 0): array
    {
        $variants = $this->catalogue->variants($query, $limit, $offset);
        $levels = $this->levels(array_column($variants, 'id'));

        return array_map(static function (array $variant) use ($levels): array {
            $variant['stock'] = $levels[(int) $variant['id']] ?? [
                'variant_id' => (int) $variant['id'], 'on_hand' => 0, 'reserved' => 0,
                'available' => 0, 'updated_at' => null,
            ];
            return $variant;
        }, $variants);
    }

    public function count(string $query = ''): int
    {
        return $this->catalogue->countVariants($query);
    }

    public function setOnHand(int $variantId, int $quantity, int $userId, string $note = ''): array
    {
        $this->assertVariant($variantId);
        if ($quantity < 0 || $quantity > self::MAX_QUANTITY) {
            throw new InvalidArgumentException(lang('Stock.invalidQuantity'));
        }
        $note = mb_substr(trim($note), 0, 255);
        $this->purgeExpired();

        $this->db->transBegin();
        try {
            $this->ensureLevel($variantId);
            $before = $this->rawLevel($variantId);
            if ($quantity < $before['reserved']) {
                throw new InvalidArgumentException(lang('Stock.belowReserved'));
            }

            $now = date('Y-m-d H:i:s');
            $this->db->query(
                'UPDATE ' . $this->db->prefixTable('stock_levels') . ' SET on_hand = ?, updated_at = ?, updated_by = ? WHERE variant_id = ? AND reserved <= ?',
                [$quantity, $now, $userId > 0 ? $userId : null, $variantId, $quantity]
            );
            if ($this->db->affectedRows() === 0) {
                $current = $this->rawLevel($variantId);
                if ($current['on_hand'] !== $quantity || $current['reserved'] > $quantity) {
                    throw new RuntimeException(lang('Stock.concurrentChange'));
                }
            }

            if ($before['on_hand'] !== $quantity) {
                $this->db->table('stock_movements')->insert([
                    'variant_id' => $variantId,
                    'change_qty' => $quantity - $before['on_hand'],
                    'before_qty' => $before['on_hand'],
                    'after_qty' => $quantity,
                    'user_id' => $userId > 0 ? $userId : null,
                    'note' => $note,
                    'created_at' => $now,
                ]);
            }

            if (! $this->db->transStatus()) {
                throw new RuntimeException(lang('Stock.saveFailed'));
            }
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        return $this->level($variantId);
    }

    public function reserve(string $key, int $variantId, int $quantity, ?DateTimeInterface $expiresAt = null): array
    {
        $this->assertVariant($variantId);
        $key = $this->reservationKey($key);
        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw new InvalidArgumentException(lang('Stock.invalidQuantity'));
        }
        $expiry = $expiresAt?->format('Y-m-d H:i:s');
        if ($expiry !== null && $expiry <= date('Y-m-d H:i:s')) {
            throw new InvalidArgumentException(lang('Stock.invalidExpiry'));
        }

        $this->purgeExpired();
        $this->db->transBegin();
        try {
            $this->ensureLevel($variantId);
            $existing = $this->db->table('stock_reservations')
                ->where('reservation_key', $key)->where('variant_id', $variantId)->get()->getRowArray();
            $oldQuantity = (int) ($existing['quantity'] ?? 0);
            $delta = $quantity - $oldQuantity;
            $now = date('Y-m-d H:i:s');

            if ($delta > 0) {
                $this->db->query(
                    'UPDATE ' . $this->db->prefixTable('stock_levels') . ' SET reserved = reserved + ?, updated_at = ? WHERE variant_id = ? AND (on_hand - reserved) >= ?',
                    [$delta, $now, $variantId, $delta]
                );
                if ($this->db->affectedRows() !== 1) {
                    throw new RuntimeException(lang('Stock.insufficient'));
                }
            } elseif ($delta < 0) {
                $release = -$delta;
                $this->db->query(
                    'UPDATE ' . $this->db->prefixTable('stock_levels') . ' SET reserved = reserved - ?, updated_at = ? WHERE variant_id = ? AND reserved >= ?',
                    [$release, $now, $variantId, $release]
                );
                if ($this->db->affectedRows() !== 1) {
                    throw new RuntimeException(lang('Stock.concurrentChange'));
                }
            }

            $record = [
                'quantity' => $quantity,
                'expires_at' => $expiry,
                'updated_at' => $now,
            ];
            if ($existing === null) {
                $this->db->table('stock_reservations')->insert([
                    ...$record,
                    'reservation_key' => $key,
                    'variant_id' => $variantId,
                    'created_at' => $now,
                ]);
            } else {
                $this->db->table('stock_reservations')->where('id', $existing['id'])->update($record);
            }

            if (! $this->db->transStatus()) {
                throw new RuntimeException(lang('Stock.saveFailed'));
            }
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        return $this->level($variantId);
    }

    public function release(string $key, ?int $variantId = null): void
    {
        $key = $this->reservationKey($key);
        $builder = $this->db->table('stock_reservations')->where('reservation_key', $key);
        if ($variantId !== null) {
            $builder->where('variant_id', $variantId);
        }
        $rows = $builder->get()->getResultArray();
        if ($rows === []) {
            return;
        }

        $this->db->transBegin();
        try {
            $this->releaseRows($rows);
            if (! $this->db->transStatus()) {
                throw new RuntimeException(lang('Stock.saveFailed'));
            }
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function consume(string $key): void
    {
        $key = $this->reservationKey($key);
        $this->purgeExpired();
        $rows = $this->db->table('stock_reservations')->where('reservation_key', $key)->get()->getResultArray();
        if ($rows === []) {
            throw new RuntimeException(lang('Stock.reservationNotFound'));
        }

        $this->db->transBegin();
        try {
            $now = date('Y-m-d H:i:s');
            foreach ($rows as $row) {
                $variantId = (int) $row['variant_id'];
                $quantity = (int) $row['quantity'];
                $before = $this->rawLevel($variantId);
                $this->db->query(
                    'UPDATE ' . $this->db->prefixTable('stock_levels') . ' SET on_hand = on_hand - ?, reserved = reserved - ?, updated_at = ? WHERE variant_id = ? AND on_hand >= ? AND reserved >= ?',
                    [$quantity, $quantity, $now, $variantId, $quantity, $quantity]
                );
                if ($this->db->affectedRows() !== 1) {
                    throw new RuntimeException(lang('Stock.concurrentChange'));
                }
                $this->db->table('stock_movements')->insert([
                    'variant_id' => $variantId,
                    'change_qty' => -$quantity,
                    'before_qty' => $before['on_hand'],
                    'after_qty' => $before['on_hand'] - $quantity,
                    'user_id' => null,
                    'note' => 'reservation:' . $key,
                    'created_at' => $now,
                ]);
            }
            $this->db->table('stock_reservations')->where('reservation_key', $key)->delete();
            if (! $this->db->transStatus()) {
                throw new RuntimeException(lang('Stock.saveFailed'));
            }
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    private function purgeExpired(): void
    {
        $rows = $this->db->table('stock_reservations')
            ->where('expires_at IS NOT NULL', null, false)
            ->where('expires_at <=', date('Y-m-d H:i:s'))
            ->get()->getResultArray();
        if ($rows === []) {
            return;
        }

        $this->db->transBegin();
        try {
            $this->releaseRows($rows);
            if (! $this->db->transStatus()) {
                throw new RuntimeException(lang('Stock.saveFailed'));
            }
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    private function releaseRows(array $rows): void
    {
        $now = date('Y-m-d H:i:s');
        foreach ($rows as $row) {
            $quantity = (int) $row['quantity'];
            $variantId = (int) $row['variant_id'];
            $this->db->query(
                'UPDATE ' . $this->db->prefixTable('stock_levels') . ' SET reserved = reserved - ?, updated_at = ? WHERE variant_id = ? AND reserved >= ?',
                [$quantity, $now, $variantId, $quantity]
            );
            if ($this->db->affectedRows() !== 1) {
                throw new RuntimeException(lang('Stock.concurrentChange'));
            }
            $this->db->table('stock_reservations')->where('id', $row['id'])->delete();
        }
    }

    private function ensureLevel(int $variantId): void
    {
        if ($this->db->table('stock_levels')->where('variant_id', $variantId)->countAllResults() > 0) {
            return;
        }
        $this->db->table('stock_levels')->insert([
            'variant_id' => $variantId,
            'on_hand' => 0,
            'reserved' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => null,
        ]);
    }

    /** @return array{variant_id:int,on_hand:int,reserved:int,available:int,updated_at:?string} */
    private function rawLevel(int $variantId): array
    {
        $row = $this->db->table('stock_levels')->where('variant_id', $variantId)->get()->getRowArray();

        return $row === null ? $this->emptyLevel($variantId) : [
            'variant_id' => $variantId,
            'on_hand' => (int) $row['on_hand'],
            'reserved' => (int) $row['reserved'],
            'available' => max(0, (int) $row['on_hand'] - (int) $row['reserved']),
            'updated_at' => $row['updated_at'] ?: null,
        ];
    }

    private function emptyLevel(int $variantId): array
    {
        return ['variant_id' => $variantId, 'on_hand' => 0, 'reserved' => 0, 'available' => 0, 'updated_at' => null];
    }

    private function assertVariant(int $variantId): void
    {
        if ($variantId < 1 || $this->catalogue->findVariant($variantId) === null) {
            throw new InvalidArgumentException(lang('Stock.variantNotFound'));
        }
    }

    private function reservationKey(string $key): string
    {
        $key = trim($key);
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,79}$/', $key) !== 1) {
            throw new InvalidArgumentException(lang('Stock.invalidReservation'));
        }

        return $key;
    }
}
