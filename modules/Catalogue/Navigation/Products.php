<?php

declare(strict_types=1);

namespace MCMS\Modules\Catalogue\Navigation;

use MCMS\Core\Navigation\PublicResourceProviderInterface;
use MCMS\Modules\Catalogue\Api\CatalogueInterface;

final class Products implements PublicResourceProviderInterface
{
    public function __construct(private readonly CatalogueInterface $catalogue)
    {
    }

    public function id(): string
    {
        return 'catalogue.product';
    }

    public function label(): string
    {
        return lang('Catalogue.navigationProducts');
    }

    public function search(string $query = '', int $limit = 50): array
    {
        return array_map(
            fn (array $product): array => $this->resource($product),
            $this->catalogue->list($query, 'published', max(1, min(100, $limit))),
        );
    }

    public function resolve(string $resourceId): ?array
    {
        if (preg_match('/^[1-9][0-9]*$/', $resourceId) !== 1) {
            return null;
        }

        $product = $this->catalogue->find((int) $resourceId);
        if ($product === null || $product['status'] !== 'published') {
            return null;
        }

        return $this->resource($product);
    }

    public function resources(): iterable
    {
        $offset = 0;
        do {
            $rows = $this->catalogue->list('', 'published', 100, $offset);
            foreach ($rows as $row) {
                yield [...$this->resource($row), 'updated_at' => (string) $row['updated_at']];
            }
            $offset += 100;
        } while (count($rows) === 100);
    }

    public function resolveUrl(string $url): ?array
    {
        if (preg_match('~^/shop/([a-z0-9]+(?:-[a-z0-9]+)*)$~', $url, $matches) !== 1) {
            return null;
        }
        $row = $this->catalogue->findPublishedBySlug($matches[1]);
        return $row === null ? null : $this->resource($row);
    }

    private function resource(array $product): array
    {
        return [
            'id' => (string) $product['id'],
            'label' => (string) $product['title'],
            'url' => (string) $product['url'],
        ];
    }
}
