<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use CodeIgniter\HTTP\DownloadResponse;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Exceptions\PageNotFoundException;
use Throwable;
use MCMS\Core\Version;
use MCMS\Core\Backup\BackupArchive;

final class Backups extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if (($denied = $this->guard('core.backups')) !== null) {
            return $denied;
        }
        try {
            $items = service('mcmsBackups')->listing();
        } catch (Throwable $e) {
            log_message('error', 'Backup list failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->to('/admin/system')->with('error', lang('MCMS.backups.failed'));
        }
        return view('admin/backups/index', [
            'title' => lang('MCMS.backups.title'),
            'adminMenu' => $this->adminMenu(),
            'user' => auth()->user(),
            'items' => $items,
            'lastRestore' => $this->lastRestore(),
        ]);
    }

    public function create(): RedirectResponse
    {
        if (($denied = $this->guard('core.backups')) !== null) {
            return $denied;
        }
        try {
            // A large site can take longer than PHP's default request time limit.
            set_time_limit(0);
            $name = service('mcmsBackups')->create();
            return redirect()->to('/admin/backups')->with(
                'message', sprintf(lang('MCMS.backups.success'), $name)
            );
        } catch (Throwable $e) {
            log_message('error', 'Backup creation failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->to('/admin/backups')->with('error', lang('MCMS.backups.failed') . ' ' . $e->getMessage());
        }
    }

    public function prepareExisting(string $name): RedirectResponse
    {
        if (($denied = $this->guard('core.backups')) !== null) {
            return $denied;
        }
        try {
            $state = service('mcmsBackupRestore')->prepareExisting($name, (string) auth()->id());
            session()->set('backup_restore_preview', $state['token']);
            return redirect()->to('/admin/backups/restore/confirm');
        } catch (Throwable $e) {
            return redirect()->to('/admin/backups')->with('error', $e->getMessage());
        }
    }

    public function prepareUpload(): RedirectResponse
    {
        if (($denied = $this->guard('core.backups')) !== null) {
            return $denied;
        }
        try {
            $upload = $this->request->getFile('archive');
            if ($upload === null || ! $upload->isValid() || ! $upload->isFile()) {
                throw new \RuntimeException('Select a valid ZIP archive.');
            }
            $state = service('mcmsBackupRestore')->prepareUpload($upload->getTempName(), (string) auth()->id());
            session()->set('backup_restore_preview', $state['token']);
            return redirect()->to('/admin/backups/restore/confirm');
        } catch (Throwable $e) {
            return redirect()->to('/admin/backups')->with('error', $e->getMessage());
        }
    }

    public function confirm(): string|RedirectResponse
    {
        if (($denied = $this->guard('core.backups')) !== null) {
            return $denied;
        }
        $token = session()->get('backup_restore_preview');
        $state = is_string($token) ? service('mcmsBackupRestore')->state($token) : null;
        if ($state === null || ($state['status'] ?? null) !== 'prepared'
            || (string) ($state['owner'] ?? '') !== (string) auth()->id()) {
            return redirect()->to('/admin/backups')->with('error', lang('MCMS.backups.restoreExpired'));
        }
        return view('admin/backups/confirm', [
            'title' => lang('MCMS.backups.restoreTitle'),
            'adminMenu' => $this->adminMenu(),
            'user' => auth()->user(),
            'state' => $state,
            'currentVersion' => Version::VERSION,
            'versionCompatible' => BackupArchive::isCompatible((string) ($state['app_version'] ?? '')),
        ]);
    }

    public function apply(): RedirectResponse
    {
        if (($denied = $this->guard('core.backups')) !== null) {
            return $denied;
        }
        try {
            $token = session()->get('backup_restore_preview');
            if (! is_string($token) || ! hash_equals($token, (string) $this->request->getPost('token'))
                || $this->request->getPost('acknowledge') !== '1') {
                throw new \RuntimeException(lang('MCMS.backups.restoreConfirmRequired'));
            }
            $restore = service('mcmsBackupRestore');
            $restore->begin($token, (string) auth()->id());
            session()->remove('backup_restore_preview');
            session()->set('backup_restore_last', $token);
            // A fresh CLI process handles module source and DB replacement;
            // it must never share the FPM request's module lock or session.
            session()->close();
            $restore->launch($token);
            session()->start();
            return redirect()->to('/admin/backups')->with('message', lang('MCMS.backups.restoreQueued'));
        } catch (Throwable $e) {
            session()->start();
            return redirect()->to('/admin/backups')->with('error', $e->getMessage());
        }
    }

    private function lastRestore(): ?array
    {
        $token = session()->get('backup_restore_last');
        return is_string($token) ? service('mcmsBackupRestore')->state($token) : null;
    }

    public function delete(string $name): RedirectResponse
    {
        if (($denied = $this->guard('core.backups')) !== null) {
            return $denied;
        }

        try {
            if (! service('mcmsBackups')->delete($name)) {
                throw PageNotFoundException::forPageNotFound();
            }

            return redirect()->to('/admin/backups')
                ->with('message', lang('MCMS.backups.deleted'));
        } catch (PageNotFoundException $e) {
            throw $e;
        } catch (Throwable $e) {
            log_message('error', 'Backup deletion failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->to('/admin/backups')
                ->with('error', lang('MCMS.backups.deleteFailed') . ' ' . $e->getMessage());
        }
    }

    public function download(string $name): DownloadResponse|RedirectResponse
    {
        if (($denied = $this->guard('core.backups')) !== null) {
            return $denied;
        }
        $file = service('mcmsBackups')->file($name);
        if ($file === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        return $this->response->download($file, null)->setFileName($name);
    }
}
