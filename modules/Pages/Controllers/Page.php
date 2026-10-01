<?php

declare(strict_types=1);

namespace MCMS\Modules\Pages\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use MCMS\Modules\Pages\Api\PageRepositoryInterface;
use RuntimeException;

final class Page extends BaseController
{
    public function show(string $slug): string
    {
        $page = $this->pages()->findPublishedBySlug($slug);

        if ($page === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $siteName = (string) (setting('Core.siteName') ?? 'mCMS');
        $pageTitle = (string) (($page['seo_title'] ?? null) ?: $page['title']);

        return service('mcmsThemes')->render(
            'page',
            [
                'page' => $page,
                'siteName' => $siteName,
                'metaTitle' => $pageTitle . ' · ' . $siteName,
                'metaDescription' => $page['seo_description'] ?? null,
            ],
            'MCMS\\Modules\\Pages\\Views\\show',
        );
    }

    private function pages(): PageRepositoryInterface
    {
        $service = service('mcmsModuleServices')->get('pages.repository');

        if (! $service instanceof PageRepositoryInterface) {
            throw new RuntimeException(lang('Pages.Errors.notFound'));
        }

        return $service;
    }
}
