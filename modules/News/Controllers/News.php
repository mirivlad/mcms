<?php

declare(strict_types=1);

namespace MCMS\Modules\News\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use MCMS\Modules\News\Api\NewsRepositoryInterface;
use RuntimeException;

final class News extends BaseController
{
    public function index(): string
    {
        $news = $this->news();
        $perPage = max(1, min(50, (int) (setting('news.per_page') ?? 10)));
        $page = max(1, (int) $this->request->getGet('page'));
        $query = mb_substr(trim((string) $this->request->getGet('q')), 0, 120);
        $categoryId = max(0, (int) $this->request->getGet('category')) ?: null;
        $tag = mb_substr(trim((string) $this->request->getGet('tag')), 0, 1000);
        $count = $news->countPublished($query, $categoryId, $tag);
        $lastPage = max(1, (int) ceil($count / $perPage));
        $page = min($page, $lastPage);

        $siteName = (string) (setting('Core.siteName') ?? 'mCMS');

        return service('mcmsThemes')->render(
            'news.index',
            [
                'articles' => $news->listPublished($query, $perPage, ($page - 1) * $perPage, $categoryId, $tag),
                'page' => $page,
                'lastPage' => $lastPage,
                'count' => $count,
                'query' => $query, 'categoryId' => $categoryId, 'tag' => $tag,
                'categories' => $news->categories(true), 'tags' => $news->tags(true),
                'newsPageUrl' => static fn (int $target): string => site_url('news') . '?' . http_build_query(['q' => $query, 'category' => $categoryId, 'tag' => $tag, 'page' => $target]),
                'siteName' => $siteName,
                'metaTitle' => lang('News.Public.title') . ' · ' . $siteName,
            ],
            'MCMS\\Modules\\News\\Views\\public_index',
        );
    }

    public function show(string $slug): string
    {
        $article = $this->news()->findPublishedBySlug($slug);
        if ($article === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $siteName = (string) (setting('Core.siteName') ?? 'mCMS');
        $title = (string) (($article['seo_title'] ?? null) ?: $article['title']);
        $article['body'] = service('mcmsShortcodes')->render($article['body']);

        return service('mcmsThemes')->render(
            'news.article',
            [
                'article' => $article,
                'siteName' => $siteName,
                'metaTitle' => $title . ' · ' . $siteName,
                'metaKeywords' => $article['seo_keywords'] ?? null,
                'metaDescription' => $article['seo_description'] ?? $article['excerpt'],
            ],
            'MCMS\\Modules\\News\\Views\\public_article',
        );
    }

    private function news(): NewsRepositoryInterface
    {
        $service = service('mcmsModuleServices')->get('news.repository');

        if (! $service instanceof NewsRepositoryInterface) {
            throw new RuntimeException(lang('News.Errors.serviceUnavailable'));
        }

        return $service;
    }
}
