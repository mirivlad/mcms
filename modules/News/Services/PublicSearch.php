<?php

declare(strict_types=1);

namespace MCMS\Modules\News\Services;

use MCMS\Core\Content\PublicSearchProviderInterface;
use MCMS\Core\Content\PublicSearch as SearchText;
use MCMS\Modules\News\Api\NewsRepositoryInterface;

final class PublicSearch implements PublicSearchProviderInterface
{
    public function __construct(private readonly NewsRepositoryInterface $repository)
    {
    }

    public function countSearch(string $query): int
    {
        return $this->repository->countPublished($query);
    }

    public function search(string $query, int $limit, int $offset): array
    {
        return array_map(static fn (array $row): array => [
            'title' => $row['title'], 'url' => $row['url'],
            'text' => SearchText::excerpt($row['body']), 'type' => lang('News.Admin.menuLabel'),
        ], $this->repository->listPublished($query, $limit, $offset));
    }
}
