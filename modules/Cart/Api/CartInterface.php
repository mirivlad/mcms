<?php

declare(strict_types=1);

namespace MCMS\Modules\Cart\Api;

interface CartInterface
{
    /** @return array{items:list<array<string,mixed>>,total_items:int,subtotal_minor:int,subtotal_display:string} */
    public function snapshot(): array;

    /** @return list<array<string,mixed>> */
    public function items(): array;

    public function count(): int;

    public function add(int $variantId, int $quantity): array;

    public function setQuantity(int $variantId, int $quantity): array;

    public function remove(int $variantId): void;

    public function clear(): void;
}
