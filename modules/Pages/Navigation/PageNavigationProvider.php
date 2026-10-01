<?php

declare(strict_types=1);

namespace MCMS\Modules\Pages\Navigation;

use MCMS\Core\Navigation\NavigableResourceProviderInterface;
use MCMS\Modules\Pages\Api\PageRepositoryInterface;

final class PageNavigationProvider implements NavigableResourceProviderInterface
{
    public function __construct(private readonly PageRepositoryInterface $pages)
    {
    }

    public function id(): string
    {
        return 'pages.page';
    }

    public function label(): string
    {
        return lang('Pages.Navigation.provider');
    }

    public function search(string $query = '', int $limit = 50): array
    {
        return array_map(
            fn (array $page): array => $this->resource($page),
            $this->pages->list($query, 'published', max(1, min(100, $limit))),
        );
    }

    public function resolve(string $resourceId): ?array
    {
        if (preg_match('/^[1-9][0-9]*$/', $resourceId) !== 1) {
            return null;
        }

        $page = $this->pages->find((int) $resourceId);
        if ($page === null || ($page['status'] ?? null) !== 'published') {
            return null;
        }

        return $this->resource($page);
    }

    private function resource(array $page): array
    {
        return [
            'id' => (string) $page['id'],
            'label' => (string) $page['title'],
            'url' => (string) $page['url'],
        ];
    }
}
