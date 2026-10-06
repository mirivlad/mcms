<?php

declare(strict_types=1);

namespace MCMS\Modules\Orders\Api;

interface OrdersInterface
{
    public function createFromCart(array $customer): array;
    public function list(string $query = '', string $status = '', int $limit = 50, int $offset = 0): array;
    public function count(string $query = '', string $status = ''): int;
    public function find(int $id): ?array;
    public function transition(int $id, string $status, int $userId, string $note = ''): array;
    public function statuses(): array;
}
