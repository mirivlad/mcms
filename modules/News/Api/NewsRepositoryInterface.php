<?php

declare(strict_types=1);

namespace MCMS\Modules\News\Api;

interface NewsRepositoryInterface
{
    public function list(string $query = '', ?string $status = null, int $limit = 100, int $offset = 0, ?int $categoryId = null, string $tag = ''): array;

    public function count(string $query = '', ?string $status = null, ?int $categoryId = null, string $tag = ''): int;

    public function listPublished(string $query = '', int $limit = 20, int $offset = 0, ?int $categoryId = null, string $tag = ''): array;

    public function countPublished(string $query = '', ?int $categoryId = null, string $tag = ''): int;

    public function categories(bool $publishedOnly = false): array;

    public function saveCategory(array $data, ?int $id = null): void;

    public function deleteCategory(int $id): void;

    public function tags(bool $publishedOnly = false): array;

    public function find(int $id): ?array;

    public function findPublishedBySlug(string $slug): ?array;

    public function preview(array $data, ?int $id = null): array;

    public function create(array $data, ?int $userId = null): array;

    public function update(int $id, array $data, ?int $userId = null): array;

    public function delete(int $id): void;

    public function url(int|array $article): string;
}
