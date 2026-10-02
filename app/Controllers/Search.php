<?php

declare(strict_types=1);

namespace App\Controllers;

use MCMS\Core\Content\PublicSearch;

final class Search extends BaseController
{
    public function index(): string
    {
        $results = (new PublicSearch(service('mcmsModuleServices')))->results(
            (string) $this->request->getGet('q'), (int) $this->request->getGet('page'),
        );
        $siteName = (string) (setting('Core.siteName') ?? 'mCMS');
        return service('mcmsThemes')->render('search.index', [
            ...$results, 'siteName' => $siteName,
            'metaTitle' => lang('MCMS.search.title') . ' · ' . $siteName,
            'metaRobots' => 'noindex, follow',
        ], 'search/index');
    }
}
