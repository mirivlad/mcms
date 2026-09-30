<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use CodeIgniter\HTTP\RedirectResponse;

final class Users extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if (($redirect = $this->guard('core.users')) !== null) {
            return $redirect;
        }

        $users = auth()->getProvider()
            ->withIdentities()
            ->withGroups()
            ->withPermissions()
            ->findAll(100);

        return view('admin/users/index', [
            'adminMenu' => $this->adminMenu(),
            'users'     => $users,
            'user'      => auth()->user(),
        ]);
    }
}
