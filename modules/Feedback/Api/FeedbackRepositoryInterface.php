<?php

declare(strict_types=1);

namespace MCMS\Modules\Feedback\Api;

interface FeedbackRepositoryInterface
{
    public function create(array $data): int;
    public function list(int $limit = 30, int $offset = 0): array;
    public function count(): int;
    public function find(int $id): ?array;
    public function markRead(int $id): void;
    public function delete(int $id): void;
}
