<?php

declare(strict_types=1);

namespace MCMS\Modules\News\Navigation;

use MCMS\Core\Navigation\PublicResourceProviderInterface;
use MCMS\Modules\News\Api\NewsRepositoryInterface;

final class NewsArticleNavigationProvider implements PublicResourceProviderInterface
{
    public function __construct(private readonly NewsRepositoryInterface $news)
    {
    }

    public function id(): string
    {
        return 'news.article';
    }

    public function label(): string
    {
        return lang('News.Navigation.articles');
    }

    public function search(string $query = '', int $limit = 50): array
    {
        return array_map(
            fn (array $article): array => $this->resource($article),
            $this->news->listPublished($query, max(1, min(100, $limit))),
        );
    }

    public function resolve(string $resourceId): ?array
    {
        if (preg_match('/^[1-9][0-9]*$/', $resourceId) !== 1) {
            return null;
        }

        $article = $this->news->find((int) $resourceId);
        if ($article === null || ! (bool) ($article['is_live'] ?? false)) {
            return null;
        }

        return $this->resource($article);
    }

    public function resources(): iterable
    {
        $offset = 0;
        do {
            $rows = $this->news->listPublished('', 100, $offset);
            foreach ($rows as $row) {
                yield $this->resource($row);
            }
            $offset += 100;
        } while (count($rows) === 100);
    }

    public function resolveUrl(string $url): ?array
    {
        if (preg_match('~^/news/([a-z0-9]+(?:-[a-z0-9]+)*)$~', $url, $matches) !== 1) {
            return null;
        }
        $row = $this->news->findPublishedBySlug($matches[1]);
        return $row === null ? null : $this->resource($row);
    }

    private function resource(array $article): array
    {
        return [
            'id' => (string) $article['id'],
            'label' => (string) $article['title'],
            'url' => (string) $article['url'],
        ];
    }
}
