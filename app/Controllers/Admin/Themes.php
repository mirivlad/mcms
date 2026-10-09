<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

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

    public function package(): string|RedirectResponse
    {
        if ($denied = $this->guard('core.themes')) {
            return $denied;
        }

        $preview = session()->get('theme_package_preview');
        if (! is_array($preview) || ($preview['owner'] ?? null) !== auth()->id()) {
            $preview = null;
        }

        return view('admin/themes/package', [
            'adminMenu' => $this->adminMenu(),
            'preview' => $preview,
        ]);
    }

    public function uploadPackage(): RedirectResponse
    {
        if ($denied = $this->guard('core.themes')) {
            return $denied;
        }

        try {
            $file = $this->request->getFile('package');
            if ($file === null || ! $file->isValid()) {
                throw new \RuntimeException(lang('MCMS.themes.uploadInvalid'));
            }
            $packages = service('mcmsThemePackages');
            $preview = $packages->prepare($file->getTempName());
            $old = session()->get('theme_package_preview');
            if (is_array($old) && ($old['owner'] ?? null) === auth()->id()) {
                $packages->cancel($old['token']);
            }
            $manifest = $preview['manifest'];
            session()->set('theme_package_preview', [
                'owner' => auth()->id(),
                'token' => $preview['token'],
                'sha256' => $preview['sha256'],
                'id' => $manifest->id,
                'name' => $manifest->name,
                'version' => $manifest->version,
                'mcms' => $manifest->mcms,
                'templates' => count($manifest->templates),
                'locations' => count($manifest->menuLocations),
                'files' => count($preview['files']),
                'bytes' => $preview['bytes'],
            ]);
        } catch (Throwable $e) {
            return redirect()->to($this->settingsPath('/admin/themes/package'))->with('error', $e->getMessage());
        }

        return redirect()->to($this->settingsPath('/admin/themes/package'));
    }

    public function installPackage(): RedirectResponse
    {
        if ($denied = $this->guard('core.themes')) {
            return $denied;
        }

        $preview = session()->get('theme_package_preview');
        if (! is_array($preview) || ($preview['owner'] ?? null) !== auth()->id()
            || ! is_string($this->request->getPost('token'))
            || ! hash_equals($preview['token'], $this->request->getPost('token'))
            || $this->request->getPost('trusted') !== '1') {
            return redirect()->to($this->settingsPath('/admin/themes/package'))->with('error', lang('MCMS.themes.confirmRequired'));
        }

        try {
            $manifest = service('mcmsThemePackages')->install($preview['token'], $preview['sha256']);
            session()->remove('theme_package_preview');
            return redirect()->to($this->settingsPath('/admin/themes'))->with('message', sprintf(lang('MCMS.themes.packageAdded'), $manifest->id));
        } catch (Throwable $e) {
            return redirect()->to($this->settingsPath('/admin/themes/package'))->with('error', $e->getMessage());
        }
    }

    public function cancelPackage(): RedirectResponse
    {
        if ($denied = $this->guard('core.themes')) {
            return $denied;
        }
        $preview = session()->get('theme_package_preview');
        if (is_array($preview) && ($preview['owner'] ?? null) === auth()->id()) {
            service('mcmsThemePackages')->cancel($preview['token']);
            session()->remove('theme_package_preview');
        }

        return redirect()->to($this->settingsPath('/admin/themes/package'));
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

                return redirect()->to($this->settingsPath('/admin/themes/' . $theme->id))->with(
                    'message',
                    lang('MCMS.messages.themeMenusSaved'),
                );
            } catch (\Throwable $e) {
                return redirect()->to($this->settingsPath('/admin/themes/' . $theme->id))->with('error', $e->getMessage());
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
            'appearance' => $manager->appearance($theme),
            'homeContent' => $manager->homeContent($theme),
            'imagesAvailable' => $manager->imagesAvailable(),
        ]);
    }

    public function appearance(string $id): RedirectResponse
    {
        if (($redirect = $this->guard('core.themes')) !== null) {
            return $redirect;
        }
        try {
            service('mcmsThemes')->saveAppearance($id, (array) $this->request->getPost('appearance'));
            return redirect()->to($this->settingsPath('/admin/themes/' . $id))->with('message', lang('MCMS.themes.appearanceSaved'));
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function images(): ResponseInterface
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        if (! $this->can('core.themes')) {
            return $this->response->setStatusCode(403)->setJSON(['error' => lang('MCMS.errors.permissionDenied')]);
        }
        return $this->response->setJSON(service('mcmsThemes')->images(
            mb_substr(trim((string) $this->request->getGet('q')), 0, 100),
            max(0, (int) $this->request->getGet('offset')),
        ));
    }

    public function home(string $id): RedirectResponse
    {
        if (($redirect = $this->guard('core.themes')) !== null) {
            return $redirect;
        }
        try {
            service('mcmsThemes')->saveHomeContent($id, (array) $this->request->getPost('home'));
            return redirect()->to($this->settingsPath('/admin/themes/' . $id))->with('message', lang('MCMS.themes.homeSaved'));
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function activate(string $id): RedirectResponse
    {
        if (($redirect = $this->guard('core.themes')) !== null) {
            return $redirect;
        }

        try {
            $theme = service('mcmsThemes')->activate($id);

            return redirect()->to($this->settingsPath('/admin/themes'))->with(
                'message',
                sprintf(lang('MCMS.messages.themeActivated'), service('mcmsThemes')->displayName($theme)),
            );
        } catch (\Throwable $e) {
            return redirect()->to($this->settingsPath('/admin/themes'))->with('error', $e->getMessage());
        }
    }
}
