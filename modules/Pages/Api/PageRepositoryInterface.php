<?php

declare(strict_types=1);

namespace MCMS\Modules\Pages\Api;

interface PageRepositoryInterface
{
    public function list(string $query = '', ?string $status = null, int $limit = 100, int $offset = 0): array;

    public function count(string $query = '', ?string $status = null): int;

    public function find(int $id): ?array;

    public function findPublishedBySlug(string $slug): ?array;

    public function create(array $data, ?int $userId = null): array;

    public function update(int $id, array $data, ?int $userId = null): array;

    public function delete(int $id): void;

    public function url(int|array $page): string;
}
