<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use CodeIgniter\HTTP\RedirectResponse;

final class Access extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if (($redirect = $this->guard('core.access')) !== null) {
            return $redirect;
        }

        return view('admin/access/index', [
            'adminMenu'   => $this->adminMenu(),
            'user'        => auth()->user(),
            'groups'      => service('mcmsAccess')->groups(),
            'permissions' => service('mcmsAccess')->permissions(),
            'matrix'      => service('mcmsAccess')->matrix(),
        ]);
    }

    public function saveGroup(): RedirectResponse
    {
        if (($redirect = $this->guard('core.access')) !== null) {
            return $redirect;
        }

        try {
            service('mcmsAccess')->saveGroup(
                (string) $this->request->getPost('id'),
                (string) $this->request->getPost('title'),
                (string) $this->request->getPost('description'),
                (array) $this->request->getPost('permissions'),
            );

            return redirect()->to('/admin/access')->with('message', lang('MCMS.messages.accessGroupSaved'));
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }
}
