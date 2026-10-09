<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

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

    public function package(): string|RedirectResponse
    {
        if ($denied = $this->guard('core.modules')) {
            return $denied;
        }

        $preview = session()->get('module_package_preview');
        if (! is_array($preview) || ($preview['owner'] ?? null) !== auth()->id()) {
            $preview = null;
        }

        return view('admin/modules/package', [
            'adminMenu' => $this->adminMenu(),
            'preview' => $preview,
        ]);
    }

    public function uploadPackage(): RedirectResponse
    {
        if ($denied = $this->guard('core.modules')) {
            return $denied;
        }

        try {
            $file = $this->request->getFile('package');
            if ($file === null || ! $file->isValid()) {
                throw new \RuntimeException(lang('MCMS.modules.uploadInvalid'));
            }
            $packages = service('mcmsModulePackages');
            $preview = $packages->prepare($file->getTempName());
            $old = session()->get('module_package_preview');
            if (is_array($old) && ($old['owner'] ?? null) === auth()->id()) {
                $packages->cancel($old['token']);
            }
            $manifest = $preview['manifest'];
            session()->set('module_package_preview', [
                'owner' => auth()->id(),
                'token' => $preview['token'],
                'sha256' => $preview['sha256'],
                'id' => $manifest->id,
                'name' => $manifest->name,
                'version' => $manifest->version,
                'mcms' => $manifest->mcms,
                'requires' => $manifest->requires,
                'files' => count($preview['files']),
                'bytes' => $preview['bytes'],
            ]);
        } catch (Throwable $e) {
            return redirect()->to('/admin/modules/package')->with('error', $e->getMessage());
        }

        return redirect()->to('/admin/modules/package');
    }

    public function installPackage(): RedirectResponse
    {
        if ($denied = $this->guard('core.modules')) {
            return $denied;
        }

        $preview = session()->get('module_package_preview');
        if (! is_array($preview) || ($preview['owner'] ?? null) !== auth()->id()
            || ! is_string($this->request->getPost('token'))
            || ! hash_equals($preview['token'], $this->request->getPost('token'))
            || $this->request->getPost('trusted') !== '1') {
            return redirect()->to('/admin/modules/package')->with('error', lang('MCMS.modules.confirmRequired'));
        }

        try {
            $manifest = service('mcmsModulePackages')->install($preview['token'], $preview['sha256']);
            session()->remove('module_package_preview');
            return redirect()->to('/admin/modules')->with('message', sprintf(lang('MCMS.modules.packageAdded'), $manifest->id));
        } catch (Throwable $e) {
            return redirect()->to('/admin/modules/package')->with('error', $e->getMessage());
        }
    }

    public function cancelPackage(): RedirectResponse
    {
        if ($denied = $this->guard('core.modules')) {
            return $denied;
        }
        $preview = session()->get('module_package_preview');
        if (is_array($preview) && ($preview['owner'] ?? null) === auth()->id()) {
            service('mcmsModulePackages')->cancel($preview['token']);
            session()->remove('module_package_preview');
        }

        return redirect()->to('/admin/modules/package');
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
