<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;

class Home extends BaseController
{
    public function index(): string|RedirectResponse
    {
        if (! service('mcmsInstaller')->isInstalled()) {
            return redirect()->to('/install');
        }

        $siteName = (string) (setting('Core.siteName') ?? 'mCMS');

        return service('mcmsThemes')->render(
            'home',
            [
                'siteName' => $siteName,
                'metaTitle' => $siteName,
            ],
            'home/index',
        );
    }
}
