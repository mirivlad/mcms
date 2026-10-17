<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use MCMS\Core\Themes\HomePageProviderInterface;
use MCMS\Core\Install\SharedHostSetup;

class Home extends BaseController
{
    public function index(): string|RedirectResponse
    {
        if (SharedHostSetup::enabled() && ! SharedHostSetup::configured()) {
            return redirect()->to('/install');
        }
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
