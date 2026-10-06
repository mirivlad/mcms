<?php

declare(strict_types=1);

namespace MCMS\Modules\Stock\Api;

use DateTimeInterface;

interface StockInterface
{
    /** @return array{variant_id:int,on_hand:int,reserved:int,available:int,updated_at:?string} */
    public function level(int $variantId): array;

    /** @return array<int,array{variant_id:int,on_hand:int,reserved:int,available:int,updated_at:?string}> */
    public function levels(array $variantIds): array;

    public function list(string $query = '', int $limit = 50, int $offset = 0): array;
    public function count(string $query = ''): int;

    /** Set the physical quantity. The value may never be lower than the currently reserved quantity. */
    public function setOnHand(int $variantId, int $quantity, int $userId, string $note = ''): array;

    /**
     * Reserve an absolute quantity for a caller-owned key.
     * Calling again with the same key/variant changes that reservation to the new quantity.
     */
    public function reserve(string $key, int $variantId, int $quantity, ?DateTimeInterface $expiresAt = null): array;

    public function release(string $key, ?int $variantId = null): void;

    /** Consume every active reservation under the key and reduce physical stock. */
    public function consume(string $key): void;
}
