<?php

declare(strict_types=1);

namespace MCMS\Modules\News\Navigation;

use MCMS\Core\Navigation\NavigableResourceProviderInterface;
use MCMS\Modules\News\Api\NewsRepositoryInterface;

final class NewsArticleNavigationProvider implements NavigableResourceProviderInterface
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

    private function resource(array $article): array
    {
        return [
            'id' => (string) $article['id'],
            'label' => (string) $article['title'],
            'url' => (string) $article['url'],
        ];
    }
}
