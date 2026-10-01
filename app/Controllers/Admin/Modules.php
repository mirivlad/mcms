<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use CodeIgniter\HTTP\RedirectResponse;

class Modules extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if (($redirect = $this->guard('core.modules')) !== null) {
            return $redirect;
        }

        $manager = service('mcmsModules');

        return view('admin/modules/index', [
            'adminMenu' => $this->adminMenu(),
            'modules'   => $manager->catalog(),
            'states'    => $manager->states(),
            'user'      => auth()->user(),
        ]);
    }

    public function enable(string $id): RedirectResponse
    {
        if (($redirect = $this->guard('core.modules')) !== null) {
            return $redirect;
        }

        try {
            service('mcmsModules')->enable($id);

            return redirect()->to('/admin/modules')->with('message', sprintf(lang('MCMS.messages.moduleEnabled'), $id));
        } catch (\Throwable $e) {
            log_message('error', 'Module enable failed: {message}', ['message' => $e->getMessage()]);

            return redirect()->to('/admin/modules')->with('error', $e->getMessage());
        }
    }

    public function disable(string $id): RedirectResponse
    {
        if (($redirect = $this->guard('core.modules')) !== null) {
            return $redirect;
        }

        try {
            service('mcmsModules')->disable($id);

            return redirect()->to('/admin/modules')->with('message', sprintf(lang('MCMS.messages.moduleDisabled'), $id));
        } catch (\Throwable $e) {
            log_message('error', 'Module disable failed: {message}', ['message' => $e->getMessage()]);

            return redirect()->to('/admin/modules')->with('error', $e->getMessage());
        }
    }
}
