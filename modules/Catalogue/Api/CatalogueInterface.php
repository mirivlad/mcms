<?php

declare(strict_types=1);

namespace MCMS\Modules\Catalogue\Api;

interface CatalogueInterface
{
    public function list(string $query = '', ?string $status = null, int $limit = 24, int $offset = 0, string $category = ''): array;
    public function count(string $query = '', ?string $status = null, string $category = ''): int;
    public function categories(bool $publishedOnly = false): array;
    public function find(int $id): ?array;
    public function findPublishedBySlug(string $slug): ?array;
    public function save(?int $id, array $data, int $userId): array;
    public function delete(int $id): void;
}
