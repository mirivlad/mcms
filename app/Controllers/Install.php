<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;
use MCMS\Core\Install\SharedHostSetup;

final class Install extends BaseController
{
    public function index(): string|RedirectResponse
    {
        if (SharedHostSetup::enabled()) {
            if (! SharedHostSetup::configured()) {
                return $this->databaseSetup();
            }
            try {
                SharedHostSetup::migrate();
            } catch (\Throwable $e) {
                log_message('error', 'Shared setup migration failed: {error}', ['error' => $e->getMessage()]);
                return view('install/shared_error', ['error' => 'Database initialization failed. Check PHP error logs and database permissions.']);
            }
        }
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
    private function databaseSetup(): string|RedirectResponse
    {
        $errors = SharedHostSetup::requirements();
        if ($this->request->getMethod() === 'POST' && $errors === []) {
            try {
                SharedHostSetup::configure([
                    'host' => $this->request->getPost('db_host'),
                    'port' => $this->request->getPost('db_port'),
                    'database' => $this->request->getPost('db_name'),
                    'username' => $this->request->getPost('db_user'),
                    'password' => $this->request->getPost('db_password'),
                    'smtp_host' => $this->request->getPost('smtp_host'),
                    'smtp_port' => $this->request->getPost('smtp_port'),
                    'smtp_sender' => $this->request->getPost('smtp_sender'),
                    'smtp_user' => $this->request->getPost('smtp_user'),
                    'smtp_password' => $this->request->getPost('smtp_password'),
                    'smtp_crypto' => $this->request->getPost('smtp_crypto'),
                ]);
                return redirect()->to('/install');
            } catch (RuntimeException $e) {
                $errors['database'] = $e->getMessage();
            }
        }
        $this->response->setHeader('Cache-Control', 'no-store');
        return view('install/shared_database', ['errors' => $errors]);
    }

}
