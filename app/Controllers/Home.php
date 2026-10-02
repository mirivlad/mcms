<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use MCMS\Core\Themes\HomePageProviderInterface;

class Home extends BaseController
{
    public function index(): string|RedirectResponse
    {
        if (! service('mcmsInstaller')->isInstalled()) {
            return redirect()->to('/install');
        }

        foreach (service('mcmsModuleServices')->all() as $provider) {
            if ($provider instanceof HomePageProviderInterface && ($html = $provider->renderHome()) !== null) {
                return $html;
            }
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
