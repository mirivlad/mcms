<?php

declare(strict_types=1);

namespace MCMS\Modules\Api\Controllers;

use App\Controllers\Admin\BaseAdminController;
use CodeIgniter\HTTP\RedirectResponse;
use MCMS\Modules\Api\Services\Keys;
use Throwable;

final class Admin extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if ($denied = $this->guard('api.manage')) {
            return $denied;
        }
        $this->response->setHeader('Cache-Control', 'private, no-store')->setHeader('Referrer-Policy', 'no-referrer');
        $user = auth()->user();
        return view('MCMS\\Modules\\Api\\Views\\index', [
            'title' => lang('Api.title'), 'adminMenu' => $this->adminMenu(),
            'keys' => $this->keys()->all(),
            'allowedScopes' => array_keys(array_filter(Keys::SCOPES, static fn (string $permission): bool => $user->can($permission))),
            'secret' => session()->getFlashdata('api_new_key'),
        ]);
    }

    public function create(): RedirectResponse
    {
        if ($denied = $this->guard('api.manage')) {
            return $denied;
        }
        try {
            $scopes = $this->request->getPost('scopes');
            $name = $this->request->getPost('name');
            $expires = $this->request->getPost('expires');
            if (! is_array($scopes) || ! is_string($name) || ! is_string($expires)) {
                throw new \InvalidArgumentException(lang('Api.invalidKey'));
            }
            $key = $this->keys()->create($name, $scopes, (int) auth()->id(), $expires);
            return redirect()->to($this->settingsPath('/admin/api'))->with('api_new_key', $key['token'])->with('message', lang('Api.created'));
        } catch (Throwable $e) {
            return redirect()->to($this->settingsPath('/admin/api'))->with('error', $e->getMessage())->withInput();
        }
    }

    public function revoke(int $id): RedirectResponse
    {
        if ($denied = $this->guard('api.manage')) {
            return $denied;
        }
        $this->keys()->revoke($id);
        return redirect()->to($this->settingsPath('/admin/api'))->with('message', lang('Api.revoked'));
    }

    private function keys(): Keys
    {
        return service('mcmsModuleServices')->get('api.keys');
    }
}
