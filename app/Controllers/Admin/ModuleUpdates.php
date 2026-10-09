<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use CodeIgniter\HTTP\DownloadResponse;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;
use Throwable;

final class ModuleUpdates extends BaseAdminController
{
    public function index(string $id): string|RedirectResponse
    {
        if ($denied = $this->guard('core.modules')) {
            return $denied;
        }
        try {
            $updates = service('mcmsModuleUpdates');
            $state = $updates->state($id);
            $module = service('mcmsModules')->catalog()[$id] ?? null;
            if ($module === null && $state === null) {
                throw new RuntimeException('Unknown module.');
            }
            $preview = session()->get('module_update_preview');
            if (! is_array($preview) || ($preview['owner'] ?? null) !== auth()->id() || ($preview['id'] ?? null) !== $id) {
                $preview = null;
            }
            return view('admin/modules/update', [
                'adminMenu' => $this->adminMenu(), 'id' => $id, 'module' => $module,
                'state' => $state, 'preview' => $preview,
                'maintenance' => service('mcmsModuleRuntime')->maintenance(),
            ]);
        } catch (Throwable $e) {
            return redirect()->to('/admin/modules')->with('error', $e->getMessage());
        }
    }

    public function upload(string $id): RedirectResponse
    {
        if ($denied = $this->guard('core.modules')) {
            return $denied;
        }
        try {
            $file = $this->request->getFile('package');
            if ($file === null || ! $file->isValid()) {
                throw new RuntimeException(lang('MCMS.modules.uploadInvalid'));
            }
            $result = service('mcmsModuleUpdates')->prepare($id, $file->getTempName());
            $old = session()->get('module_update_preview');
            if (is_array($old) && ($old['owner'] ?? null) === auth()->id()) {
                service('mcmsModulePackages')->cancel($old['token']);
            }
            $manifest = $result['manifest'];
            session()->set('module_update_preview', [
                'owner' => auth()->id(), 'id' => $id, 'token' => $result['token'],
                'sha256' => $result['sha256'], 'version' => $manifest->version,
                'mcms' => $manifest->mcms, 'requires' => $manifest->requires,
                'files' => count($result['files']), 'bytes' => $result['bytes'],
            ]);
            return $this->redirect($id);
        } catch (Throwable $e) {
            return $this->redirect($id)->with('error', $e->getMessage());
        }
    }

    public function queue(string $id): RedirectResponse
    {
        if ($denied = $this->guard('core.modules')) {
            return $denied;
        }
        try {
            $preview = session()->get('module_update_preview');
            $token = $this->request->getPost('token');
            if (! is_array($preview) || ($preview['owner'] ?? null) !== auth()->id() || ($preview['id'] ?? null) !== $id
                || ! is_string($token) || ! hash_equals($preview['token'], $token) || $this->request->getPost('trusted') !== '1') {
                throw new RuntimeException(lang('MCMS.modules.confirmRequired'));
            }
            service('mcmsModuleUpdates')->queue($id, $token, $preview['sha256']);
            session()->remove('module_update_preview');
            return $this->redirect($id)->with('message', lang('MCMS.modules.updatePrepared'));
        } catch (Throwable $e) {
            return $this->redirect($id)->with('error', $e->getMessage());
        }
    }

    public function cancel(string $id): RedirectResponse
    {
        if ($denied = $this->guard('core.modules')) {
            return $denied;
        }
        try {
            service('mcmsModuleUpdates')->cancel($id, (string) $this->request->getPost('operation'));
            return $this->redirect($id)->with('message', lang('MCMS.modules.updateCancelled'));
        } catch (Throwable $e) {
            return $this->redirect($id)->with('error', $e->getMessage());
        }
    }

    public function apply(string $id): RedirectResponse
    {
        return $this->operate($id, 'apply', 'backup');
    }

    public function restore(string $id): RedirectResponse
    {
        return $this->operate($id, 'restore', 'database_restored');
    }

    public function snapshot(string $id): DownloadResponse|RedirectResponse
    {
        if ($denied = $this->guard('core.modules')) {
            return $denied;
        }
        try {
            return $this->response->download(service('mcmsModuleUpdates')->snapshot($id), null)
                ->setFileName($id . '-previous.zip')->setHeader('Cache-Control', 'no-store');
        } catch (Throwable $e) {
            return $this->redirect($id)->with('error', $e->getMessage());
        }
    }

    private function operate(string $id, string $action, string $confirmation): RedirectResponse
    {
        if ($denied = $this->guard('core.modules')) {
            return $denied;
        }
        try {
            if ($this->request->getPost($confirmation) !== '1') {
                throw new RuntimeException(lang('MCMS.modules.backupRequired'));
            }
            $operation = (string) $this->request->getPost('operation');
            $updates = service('mcmsModuleUpdates');
            $updates->begin($id, $operation, $action);
            // Release the session too: recovery must remain reachable if this request is interrupted.
            session()->close();
            $updates->launch($id, $operation, $action);
            session()->start();
            return $this->redirect($id)->with('message', lang('MCMS.modules.' . ($action === 'apply' ? 'updateApplied' : 'updateRestored')));
        } catch (Throwable $e) {
            session()->start();
            return $this->redirect($id)->with('error', $e->getMessage());
        }
    }

    private function redirect(string $id): RedirectResponse
    {
        return redirect()->to('/admin/modules/' . rawurlencode($id) . '/update');
    }
}
