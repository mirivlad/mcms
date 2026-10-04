<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

final class Install extends BaseController
{
    public function index(): string|RedirectResponse
    {
        $installer = service('mcmsInstaller');

        if ($installer->isInstalled()) {
            return redirect()->to('/login');
        }

        if ($this->request->getMethod() === 'POST') {
            $siteName = trim((string) $this->request->getPost('site_name'));
            $email = trim((string) $this->request->getPost('email'));
            $username = trim((string) $this->request->getPost('username'));
            $password = (string) $this->request->getPost('password');
            $passwordConfirm = (string) $this->request->getPost('password_confirm');
            $allowRegistration = $this->request->getPost('allow_registration') === '1';
            $locale = trim((string) $this->request->getPost('locale'));
            $supportedLocales = config('App')->supportedLocales;

            if (! in_array($locale, $supportedLocales, true)) {
                return redirect()->back()
                    ->withInput()
                    ->with('errors', ['locale' => lang('MCMS.errors.invalidLocale')]);
            }

            $this->request->setLocale($locale);
            service('language')->setLocale($locale);

            $validation = service('validation');
            $validation->setRules([
                'site_name' => [
                    'label' => lang('MCMS.install.siteName'),
                    'rules' => 'required|max_length[120]',
                ],
                'email' => [
                    'label' => lang('MCMS.users.email'),
                    'rules' => 'required|valid_email|max_length[254]',
                ],
                'username' => [
                    'label' => lang('MCMS.users.username'),
                    'rules' => 'required|min_length[3]|max_length[30]|regex_match[/\A[a-zA-Z0-9\.]+\z/]',
                ],
                'password' => [
                    'label' => lang('MCMS.install.password'),
                    'rules' => 'required|min_length[8]',
                ],
                'password_confirm' => [
                    'label' => lang('MCMS.install.passwordConfirm'),
                    'rules' => 'required|matches[password]',
                ],
            ]);

            if (! $validation->run([
                'site_name' => $siteName,
                'email' => $email,
                'username' => $username,
                'password' => $password,
                'password_confirm' => $passwordConfirm,
            ])) {
                return redirect()->back()
                    ->withInput()
                    ->with('errors', $validation->getErrors());
            }

            try {
                $user = $installer->createSuperadmin(
                    $email,
                    $username,
                    $password,
                    $siteName,
                    $allowRegistration,
                    $locale,
                );

                auth('session')->getAuthenticator()->login($user);

                service('mcmsDemoData')->seed();

                return redirect()->to('/admin')
                    ->with('message', lang('MCMS.messages.installComplete'));
            } catch (RuntimeException $e) {
                return redirect()->back()
                    ->withInput()
                    ->with('errors', ['install' => $e->getMessage()]);
            }
        }

        return view('install/index');
    }
}
