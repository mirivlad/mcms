<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

class Dashboard extends BaseAdminController
{
    public function index(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        if (($redirect = $this->guard('core.admin')) !== null) {
            return $redirect;
        }

        return view('admin/dashboard', [
            'adminMenu' => $this->adminMenu(),
            'widgets'   => $this->adminWidgets(),
            'user'      => auth()->user(),
        ]);
    }
}
