<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\RedirectResponse;

final class Settings extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if (($redirect = $this->guard('core.settings')) !== null) {
            return $redirect;
        }

        if ($this->request->getMethod() === 'POST') {
            $siteName = trim((string) $this->request->getPost('site_name'));
            if ($siteName === '' || mb_strlen($siteName) > 120) {
                return redirect()->back()->withInput()->with('error', 'Site name must be between 1 and 120 characters.');
            }

            $allowRegistration = $this->request->getPost('allow_registration') === '1';
            $settings = service('settings');
            $settings->set('Core.siteName', $siteName);
            $settings->set('Auth.allowRegistration', $allowRegistration);

            Events::trigger('mcms.settings.changed', ['Core.siteName', 'Auth.allowRegistration']);

            return redirect()->to('/admin/settings')->with('message', 'Settings saved.');
        }

        return view('admin/settings/index', [
            'adminMenu'         => $this->adminMenu(),
            'siteName'          => setting('Core.siteName'),
            'allowRegistration' => (bool) setting('Auth.allowRegistration'),
            'user'              => auth()->user(),
        ]);
    }
}
