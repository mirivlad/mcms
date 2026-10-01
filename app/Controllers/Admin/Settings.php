<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\RedirectResponse;
use MCMS\Core\Events\CoreEvents;

final class Settings extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if (($redirect = $this->guard('core.settings')) !== null) {
            return $redirect;
        }

        $app = config('App');
        $supportedLocales = $app->supportedLocales;

        if ($this->request->getMethod() === 'POST') {
            $siteName = trim((string) $this->request->getPost('site_name'));
            $locale = trim((string) $this->request->getPost('locale'));

            if ($siteName === '' || mb_strlen($siteName) > 120) {
                return redirect()->back()->withInput()->with('error', lang('MCMS.errors.invalidSiteName'));
            }

            if (! in_array($locale, $supportedLocales, true)) {
                return redirect()->back()->withInput()->with('error', lang('MCMS.errors.invalidLocale'));
            }

            $allowRegistration = $this->request->getPost('allow_registration') === '1';
            $settings = service('settings');
            $settings->set('Core.siteName', $siteName);
            $settings->set('Core.locale', $locale);
            $settings->set('Auth.allowRegistration', $allowRegistration);

            $this->request->setLocale($locale);
            service('language')->setLocale($locale);

            Events::trigger(CoreEvents::SETTINGS_CHANGED, [
                'Core.siteName',
                'Core.locale',
                'Auth.allowRegistration',
            ]);

            return redirect()->to('/admin/settings')->with('message', lang('MCMS.messages.settingsSaved'));
        }

        $locale = (string) (setting('Core.locale') ?? $app->defaultLocale);
        if (! in_array($locale, $supportedLocales, true)) {
            $locale = $app->defaultLocale;
        }

        return view('admin/settings/index', [
            'adminMenu'         => $this->adminMenu(),
            'siteName'          => setting('Core.siteName'),
            'locale'            => $locale,
            'supportedLocales'  => $supportedLocales,
            'allowRegistration' => (bool) setting('Auth.allowRegistration'),
            'user'              => auth()->user(),
        ]);
    }
}
