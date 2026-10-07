<?php

declare(strict_types=1);

namespace MCMS\Modules\Delivery\Api;

interface DeliveryInterface
{
    /** @return list<array<string,mixed>> */
    public function all(bool $enabledOnly = false): array;

    /** @return list<array<string,mixed>> */
    public function selectable(): array;

    public function find(int $id): ?array;

    public function findSelectable(int $id): ?array;

    public function save(?int $id, array $data): array;

    public function delete(int $id): void;

    public function reorder(array $positions): void;
}
