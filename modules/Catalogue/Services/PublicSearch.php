<?php

declare(strict_types=1);

namespace MCMS\Modules\Catalogue\Services;

use MCMS\Core\Content\PublicSearchProviderInterface;
use MCMS\Core\Content\PublicSearch as SearchText;
use MCMS\Modules\Catalogue\Api\CatalogueInterface;

final class PublicSearch implements PublicSearchProviderInterface
{
    public function __construct(private readonly CatalogueInterface $repository)
    {
    }

    public function countSearch(string $query): int
    {
        return $this->repository->count($query, 'published');
    }

    public function search(string $query, int $limit, int $offset): array
    {
        return array_map(static fn (array $row): array => [
            'title' => $row['title'], 'url' => $row['url'],
            'text' => SearchText::excerpt($row['body']), 'type' => lang('Catalogue.name'),
        ], $this->repository->list($query, 'published', $limit, $offset));
    }
}
