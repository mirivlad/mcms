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
        $count = $news->countPublished();
        $lastPage = max(1, (int) ceil($count / $perPage));
        $page = min($page, $lastPage);

        $siteName = (string) (setting('Core.siteName') ?? 'mCMS');

        return service('mcmsThemes')->render(
            'news.index',
            [
                'articles' => $news->listPublished('', $perPage, ($page - 1) * $perPage),
                'page' => $page,
                'lastPage' => $lastPage,
                'count' => $count,
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
