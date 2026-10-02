<?php

declare(strict_types=1);

namespace MCMS\Modules\Pages\Services;

use MCMS\Core\Content\PublicSearchProviderInterface;
use MCMS\Core\Content\PublicSearch as SearchText;
use MCMS\Modules\Pages\Api\PageRepositoryInterface;

final class PublicSearch implements PublicSearchProviderInterface
{
    public function __construct(private readonly PageRepositoryInterface $repository)
    {
    }

    public function countSearch(string $query): int
    {
        return $this->repository->countSearchPublished($query);
    }

    public function search(string $query, int $limit, int $offset): array
    {
        return array_map(static fn (array $row): array => [
            'title' => $row['title'], 'url' => $row['url'],
            'text' => SearchText::excerpt($row['body']), 'type' => lang('Pages.Admin.menuLabel'),
        ], $this->repository->searchPublished($query, $limit, $offset));
    }
}
