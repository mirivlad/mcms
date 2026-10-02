<?php

declare(strict_types=1);

namespace MCMS\Modules\Pages\Services;

use MCMS\Core\Themes\HomePageProviderInterface;
use MCMS\Modules\Pages\Api\PageRepositoryInterface;

final class PageHomeProvider implements HomePageProviderInterface
{
    public function __construct(private readonly PageRepositoryInterface $pages)
    {
    }

    public function renderHome(): ?string
    {
        $id = (int) (setting('pages.home_page_id') ?? 0);
        $page = $id > 0 ? $this->pages->find($id) : null;
        if ($page === null || $page['status'] !== 'published') {
            return null;
        }
        $siteName = (string) (setting('Core.siteName') ?? 'mCMS');
        $page['body'] = service('mcmsShortcodes')->render($page['body']);
        return service('mcmsThemes')->render('page', [
            'page' => $page, 'siteName' => $siteName,
            'metaTitle' => (($page['seo_title'] ?? null) ?: $page['title']) . ' · ' . $siteName,
            'metaDescription' => $page['seo_description'],
        ], 'MCMS\\Modules\\Pages\\Views\\show');
    }
}
