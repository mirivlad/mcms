<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class SiteLocale implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // An unconfigured shared installation must not attempt Docker's default
        // database hostname on every anonymous request to the setup wizard.
        if (\MCMS\Core\Install\SharedHostSetup::enabled()
            && ! \MCMS\Core\Install\SharedHostSetup::configured()) {
            return null;
        }
        try {
            if (! service('mcmsInstaller')->isInstalled()) {
                return null;
            }

            // Discover module manifests before the Language service resolves
            // the active locale. This registers namespaces for disabled and
            // not-yet-installed modules without booting them or running schema.
            service('mcmsModules')->catalog();

            $app = config('App');
            $locale = (string) (service('settings')->get('Core.locale') ?? $app->defaultLocale);

            if (! in_array($locale, $app->supportedLocales, true)) {
                $locale = $app->defaultLocale;
            }

            if (method_exists($request, 'setLocale')) {
                $request->setLocale($locale);
            }

            service('language')->setLocale($locale);
        } catch (\Throwable) {
            // During a clean install the database/settings tables may not exist yet.
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
