<?php

declare(strict_types=1);

namespace MCMS\Modules\Catalogue\Navigation;

use MCMS\Core\Navigation\PublicResourceProviderInterface;
use MCMS\Modules\Catalogue\Services\Categories as Repository;

final class Categories implements PublicResourceProviderInterface
{
    public function __construct(private readonly Repository $categories)
    {
    }

    public function id(): string
    {
        return 'catalogue.category';
    }

    public function label(): string
    {
        return lang('Catalogue.categories');
    }

    public function search(string $query = '', int $limit = 50): array
    {
        $query = mb_strtolower(trim($query));
        $rows = array_filter($this->categories->list(true), static fn (array $row): bool => $query === '' || str_contains(mb_strtolower($row['path']), $query));
        return array_map($this->resource(...), array_slice(array_values($rows), 0, max(1, min(100, $limit))));
    }

    public function resolve(string $resourceId): ?array
    {
        if (preg_match('/^[1-9][0-9]*$/', $resourceId) !== 1) {
            return null;
        }
        foreach ($this->categories->list(true) as $row) {
            if ($row['id'] === (int) $resourceId) {
                return $this->resource($row);
            }
        }
        return null;
    }

    public function resources(): iterable
    {
        foreach ($this->categories->list(true) as $row) {
            yield [...$this->resource($row), 'updated_at' => $row['updated_at']];
        }
    }

    public function resolveUrl(string $url): ?array
    {
        if (preg_match('~^/shop/category/([a-z0-9]+(?:-[a-z0-9]+)*)$~', $url, $match) !== 1) {
            return null;
        }
        $row = $this->categories->bySlug($match[1]);
        return $row === null ? null : $this->resolve((string) $row['id']);
    }

    private function resource(array $row): array
    {
        return ['id' => (string) $row['id'], 'label' => $row['path'], 'url' => $row['url']];
    }
}
