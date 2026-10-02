<?php

declare(strict_types=1);

namespace MCMS\Modules\Pages\Navigation;

use MCMS\Core\Navigation\PublicResourceProviderInterface;
use MCMS\Modules\Pages\Api\PageRepositoryInterface;

final class PageNavigationProvider implements PublicResourceProviderInterface
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

    public function resources(): iterable
    {
        $homeId = (int) (setting('pages.home_page_id') ?? 0);
        $offset = 0;
        do {
            $rows = $this->pages->list('', 'published', 100, $offset);
            foreach ($rows as $row) {
                $resource = [...$this->resource($row), 'updated_at' => (string) $row['updated_at']];
                yield $resource;
                if ((int) $row['id'] === $homeId) {
                    yield [...$resource, 'url' => '/'];
                }
            }
            $offset += 100;
        } while (count($rows) === 100);
    }

    public function resolveUrl(string $url): ?array
    {
        if (preg_match('~^/([a-z0-9]+(?:-[a-z0-9]+)*)$~', $url, $matches) !== 1) {
            return null;
        }
        $row = $this->pages->findPublishedBySlug($matches[1]);
        return $row === null ? null : $this->resource($row);
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
