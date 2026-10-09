<?php

declare(strict_types=1);

namespace MCMS\Modules\Stock\Api;

interface StockSyncInterface extends StockInterface
{
    /** Absolute physical stock update; reservations stay owned by Stock. */
    public function compareAndSetOnHand(int $variantId, int $expected, int $quantity, int $userId, string $note = ''): array;
}
