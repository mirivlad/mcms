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
            'adminMenu'       => $this->adminMenu(),
            'user'            => auth()->user(),
            'groups'          => service('mcmsAccess')->groups(),
            'permissions'     => service('mcmsAccess')->permissions(),
            'matrix'          => service('mcmsAccess')->matrix(),
            'protectedGroups' => service('mcmsAccess')->protectedGroups(),
            'memberCounts'    => service('mcmsAccess')->groupMemberCounts(),
        ]);
    }

    public function saveGroup(): RedirectResponse
    {
        if (($redirect = $this->guard('core.access')) !== null) {
            return $redirect;
        }

        try {
            $mode = (string) $this->request->getPost('mode');
            if (! in_array($mode, ['create', 'edit'], true)) {
                throw new \InvalidArgumentException(lang('MCMS.errors.groupAction'));
            }
            service('mcmsAccess')->saveGroup(
                (string) $this->request->getPost('id'),
                (string) $this->request->getPost('title'),
                (string) $this->request->getPost('description'),
                (array) $this->request->getPost('permissions'),
                $mode === 'create',
            );

            return redirect()->to('/admin/access')->with('message', lang('MCMS.messages.accessGroupSaved'));
        } catch (\Throwable $e) {
            return redirect()->to('/admin/access')->withInput()
                ->with('accessGroupMode', $mode ?? 'create')->with('error', $e->getMessage());
        }
    }

    public function deleteGroup(): RedirectResponse
    {
        if (($redirect = $this->guard('core.access')) !== null) {
            return $redirect;
        }

        try {
            if ($this->request->getPost('confirm') !== '1') {
                throw new \InvalidArgumentException(lang('MCMS.errors.groupDeleteConfirmation'));
            }
            service('mcmsAccess')->deleteGroup((string) $this->request->getPost('id'));

            return redirect()->to('/admin/access')->with('message', lang('MCMS.messages.accessGroupDeleted'));
        } catch (\Throwable $e) {
            return redirect()->to('/admin/access')->with('error', $e->getMessage());
        }
    }
}
