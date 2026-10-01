<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use CodeIgniter\Exceptions\PageNotFoundException;
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

    public function configure(string $id): string|RedirectResponse
    {
        if (($redirect = $this->guard('core.themes')) !== null) {
            return $redirect;
        }

        $manager = service('mcmsThemes');
        $theme = $manager->find($id);
        if ($theme === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if ($this->request->getMethod() === 'POST') {
            $locations = $this->request->getPost('location');
            $menuIds = $this->request->getPost('menu_id');
            $locations = is_array($locations) ? $locations : [];
            $menuIds = is_array($menuIds) ? $menuIds : [];

            $assignments = [];
            foreach ($locations as $index => $locationId) {
                if (! is_string($locationId) || ! isset($theme->menuLocations[$locationId])) {
                    continue;
                }

                $assignments[$locationId] = $menuIds[$index] ?? null;
            }

            try {
                $manager->saveMenuAssignments($theme->id, $assignments);

                return redirect()->to('/admin/themes/' . $theme->id)->with(
                    'message',
                    lang('MCMS.messages.themeMenusSaved'),
                );
            } catch (\Throwable $e) {
                return redirect()->to('/admin/themes/' . $theme->id)->with('error', $e->getMessage());
            }
        }

        return view('admin/themes/configure', [
            'title' => sprintf(lang('MCMS.themes.configureTitle'), $manager->displayName($theme)),
            'adminMenu' => $this->adminMenu(),
            'user' => auth()->user(),
            'theme' => $theme,
            'activeId' => $manager->activeId(),
            'manager' => $manager,
            'menusAvailable' => $manager->menusAvailable(),
            'menus' => $manager->availableMenus(),
            'assignments' => $manager->menuAssignments($theme),
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
