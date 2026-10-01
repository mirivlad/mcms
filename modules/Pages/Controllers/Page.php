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

        return service('mcmsThemes')->render(
            'page',
            ['page' => $page],
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
