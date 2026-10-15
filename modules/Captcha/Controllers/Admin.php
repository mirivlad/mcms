<?php

declare(strict_types=1);

namespace MCMS\Modules\Captcha\Controllers;

use App\Controllers\Admin\BaseAdminController;
use CodeIgniter\HTTP\RedirectResponse;
use MCMS\Modules\Captcha\Services\Captcha;
use InvalidArgumentException;

final class Admin extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if ($denied = $this->guard('captcha.manage')) {
            return $denied;
        }
        $captcha = service('mcmsModuleServices')->get('captcha.verifier');
        if ($this->request->getMethod() === 'POST') {
            try {
                $captcha->saveOptions((array) $this->request->getPost());
                return redirect()->to($this->settingsPath('/admin/captcha'))->with('message', lang('Captcha.Settings.saved'));
            } catch (InvalidArgumentException $e) {
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }
        }
        return view('MCMS\Modules\Captcha\Views\settings', [
            'adminMenu' => $this->adminMenu(), 'user' => auth()->user(),
            'options' => $captcha->options(), 'statistics' => $captcha->statistics(), 'purposes' => Captcha::PURPOSES,
        ]);
    }
}
