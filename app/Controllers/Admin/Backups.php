<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use CodeIgniter\HTTP\DownloadResponse;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Exceptions\PageNotFoundException;
use Throwable;

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
