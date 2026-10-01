<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use CodeIgniter\HTTP\RedirectResponse;

final class Themes extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if (($redirect = $this->guard('core.themes')) !== null) {
            return $redirect;
        }

        $manager = service('mcmsThemes');

        return view('admin/themes/index', [
            'title' => lang('MCMS.themes.title'),
            'adminMenu' => $this->adminMenu(),
            'user' => auth()->user(),
            'themes' => $manager->catalog(),
            'activeId' => $manager->activeId(),
            'manager' => $manager,
        ]);
    }

    public function activate(string $id): RedirectResponse
    {
        if (($redirect = $this->guard('core.themes')) !== null) {
            return $redirect;
        }

        try {
            $theme = service('mcmsThemes')->activate($id);

            return redirect()->to('/admin/themes')->with(
                'message',
                sprintf(lang('MCMS.messages.themeActivated'), service('mcmsThemes')->displayName($theme)),
            );
        } catch (\Throwable $e) {
            return redirect()->to('/admin/themes')->with('error', $e->getMessage());
        }
    }
}