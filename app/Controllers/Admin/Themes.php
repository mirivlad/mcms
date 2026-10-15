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
            'updates' => service('mcmsThemeUpdates'),
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

    public function update(string $id): string|RedirectResponse
    {
        if ($denied = $this->guard('core.themes')) {
            return $denied;
        }
        $theme = service('mcmsThemes')->find($id);
        if ($theme === null || ! $theme->external) {
            throw PageNotFoundException::forPageNotFound();
        }
        if ($this->request->getMethod() === 'POST') {
            try {
                $file = $this->request->getFile('package');
                if ($file === null || ! $file->isValid()) {
                    throw new \RuntimeException(lang('MCMS.themes.uploadInvalid'));
                }
                $preview = service('mcmsThemeUpdates')->prepare($id, $file->getTempName());
                $old = session()->get('theme_update_preview');
                if (is_array($old) && ($old['owner'] ?? null) === auth()->id()) {
                    service('mcmsThemePackages')->cancel($old['token']);
                }
                session()->set('theme_update_preview', [
                    'owner' => auth()->id(), 'id' => $id, 'token' => $preview['token'],
                    'sha256' => $preview['sha256'], 'source' => $preview['source'],
                    'current' => $preview['current'], 'version' => $preview['manifest']->version,
                    'description' => $preview['manifest']->description,
                ]);
                return redirect()->to($this->settingsPath('/admin/themes/' . $id . '/update'));
            } catch (Throwable $e) {
                return redirect()->to($this->settingsPath('/admin/themes/' . $id . '/update'))->with('error', $e->getMessage());
            }
        }
        $preview = session()->get('theme_update_preview');
        if (! is_array($preview) || ($preview['owner'] ?? null) !== auth()->id() || ($preview['id'] ?? null) !== $id) {
            $preview = null;
        }
        return view('admin/themes/update', [
            'adminMenu' => $this->adminMenu(), 'theme' => $theme, 'preview' => $preview,
            'previous' => service('mcmsThemeUpdates')->previous($id),
            'source' => \MCMS\Core\Modules\ModulePackages::fingerprint($theme->path),
        ]);
    }

    public function applyUpdate(string $id): RedirectResponse
    {
        if ($denied = $this->guard('core.themes')) {
            return $denied;
        }
        $preview = session()->get('theme_update_preview');
        try {
            if (! is_array($preview) || ($preview['owner'] ?? null) !== auth()->id() || ($preview['id'] ?? null) !== $id
                || ! is_string($this->request->getPost('token'))
                || ! hash_equals($preview['token'], $this->request->getPost('token'))
                || $this->request->getPost('trusted') !== '1') {
                throw new \RuntimeException(lang('MCMS.themes.confirmRequired'));
            }
            service('mcmsThemeUpdates')->apply($id, $preview['token'], $preview['sha256'], $preview['source']);
            session()->remove('theme_update_preview');
            return redirect()->to($this->settingsPath('/admin/themes'))->with('message', lang('MCMS.themes.updated'));
        } catch (Throwable $e) {
            return redirect()->to($this->settingsPath('/admin/themes/' . $id . '/update'))->with('error', $e->getMessage());
        }
    }

    public function cancelUpdate(string $id): RedirectResponse
    {
        if ($denied = $this->guard('core.themes')) {
            return $denied;
        }
        $preview = session()->get('theme_update_preview');
        if (is_array($preview) && ($preview['owner'] ?? null) === auth()->id() && ($preview['id'] ?? null) === $id) {
            service('mcmsThemePackages')->cancel($preview['token']);
            session()->remove('theme_update_preview');
        }
        return redirect()->to('/admin/themes/' . rawurlencode($id) . '/update');
    }

    public function rollback(string $id): RedirectResponse
    {
        if ($denied = $this->guard('core.themes')) {
            return $denied;
        }
        try {
            if ($this->request->getPost('trusted') !== '1') {
                throw new \RuntimeException(lang('MCMS.themes.confirmRequired'));
            }
            service('mcmsThemeUpdates')->rollback($id, (string) $this->request->getPost('source'));
            session()->remove('theme_update_preview');
            return redirect()->to($this->settingsPath('/admin/themes'))->with('message', lang('MCMS.themes.updated'));
        } catch (Throwable $e) {
            return redirect()->to($this->settingsPath('/admin/themes/' . $id . '/update'))->with('error', $e->getMessage());
        }
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

            $extraAssignments = [];
            $extraLocations = $this->request->getPost('extra_location');
            $extraSources = $this->request->getPost('extra_source');
            $extraVisibility = $this->request->getPost('extra_visibility');
            $extraTypes = $this->request->getPost('extra_target_type');
            $extraIds = $this->request->getPost('extra_target_id');
            if (is_array($extraLocations) && is_array($extraSources) && is_array($extraVisibility)) {
                foreach ($extraLocations as $index => $locationId) {
                    if (! is_string($locationId) || ! isset($theme->menuLocations[$locationId])) {
                        continue;
                    }
                    $extraAssignments[$locationId][] = [
                        'source' => is_string($extraSources[$index] ?? null) ? $extraSources[$index] : '',
                        'visibility' => is_string($extraVisibility[$index] ?? null) ? $extraVisibility[$index] : 'all',
                        'target_type' => is_array($extraTypes) && is_string($extraTypes[$index] ?? null) ? $extraTypes[$index] : '',
                        'target_id' => is_array($extraIds) && is_string($extraIds[$index] ?? null) ? $extraIds[$index] : '',
                    ];
                }
            }
            try {
                if ($this->request->getPost('menu_form_version') === '2' && $this->request->getPost('menu_form_complete') !== '1') {
                    throw new \RuntimeException(lang('MCMS.themes.menuFormTruncated'));
                }
                $manager->saveNavigationAssignments($theme->id, $manager->menusAvailable() ? $assignments : null, $extraAssignments);

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
            'extraSources' => $manager->availableExtraMenuSources(),
            'navigationProviders' => service('mcmsNavigation')->choices(),
            'extraAssignments' => $manager->extraMenuAssignments($theme),
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

    public function resources(): ResponseInterface
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        if (! $this->can('core.themes')) {
            return $this->response->setStatusCode(403)->setJSON(['items' => []]);
        }
        $registry = service('mcmsNavigation');
        $type = (string) $this->request->getGet('provider');
        if (! $registry->has($type)) {
            return $this->response->setStatusCode(404)->setJSON(['items' => []]);
        }
        return $this->response->setJSON(['items' => $registry->get($type)->search(
            mb_substr(trim((string) $this->request->getGet('q')), 0, 100), 100,
        )]);
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
