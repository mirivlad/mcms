<?php

declare(strict_types=1);

namespace MCMS\Modules\Notifications\Controllers;

use App\Controllers\Admin\BaseAdminController;
use CodeIgniter\HTTP\RedirectResponse;
use MCMS\Modules\Notifications\Services\Notifications;
use Throwable;

final class Admin extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if (($redirect = $this->guard('notifications.manage')) !== null) {
            return $redirect;
        }
        return view('MCMS\\Modules\\Notifications\\Views\\settings', [
            'title' => lang('Notifications.title'), 'adminMenu' => $this->adminMenu(), 'user' => auth()->user(),
            'commentPreferences' => $this->mail()->commentPreferences(),
            'preferences' => $this->mail()->preferences(), 'recent' => $this->mail()->recent(),
            'pending' => $this->mail()->pendingCount(), 'transport' => config('Email'),
        ]);
    }

    public function save(): RedirectResponse
    {
        if (($redirect = $this->guard('notifications.manage')) !== null) {
            return $redirect;
        }
        try {
            $this->mail()->save((array) $this->request->getPost());
            return $this->back()->with('message', lang('Notifications.saved'));
        } catch (Throwable $e) {
            return $this->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function comments(): RedirectResponse
    {
        if ($denied = $this->guard('notifications.manage')) {
            return $denied;
        }
        try {
            $this->mail()->saveCommentPreferences((array) $this->request->getPost());
            return $this->back()->with('message', lang('Notifications.saved'));
        } catch (Throwable $e) {
            return $this->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function test(): RedirectResponse
    {
        if (($redirect = $this->guard('notifications.manage')) !== null) {
            return $redirect;
        }
        try {
            return $this->mail()->test()
                ? $this->back()->with('message', lang('Notifications.testSent'))
                : $this->back()->with('error', lang('Notifications.sendFailed'));
        } catch (Throwable $e) {
            return $this->back()->with('error', $e->getMessage());
        }
    }

    public function retry(): RedirectResponse
    {
        if (($redirect = $this->guard('notifications.manage')) !== null) {
            return $redirect;
        }
        $result = $this->mail()->sendPending();
        return $this->back()->with($result['failed'] > 0 ? 'error' : 'message', lang('Notifications.retryResult', [$result['sent'], $result['failed']]));
    }

    private function back(): RedirectResponse
    {
        return redirect()->to($this->settingsPath('/admin/notifications'));
    }

    private function mail(): Notifications
    {
        return service('mcmsModuleServices')->get('notifications.mail');
    }
}
