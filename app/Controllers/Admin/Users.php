<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Entities\User;
use MCMS\Core\Events\CoreEvents;
use RuntimeException;

final class Users extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if (($redirect = $this->guard('core.users')) !== null) {
            return $redirect;
        }

        $users = auth()->getProvider()
            ->withIdentities()
            ->withGroups()
            ->withPermissions()
            ->findAll(100);

        return view('admin/users/index', [
            'adminMenu' => $this->adminMenu(),
            'users'     => $users,
            'user'      => auth()->user(),
            'canAccess' => $this->can('core.access'),
        ]);
    }

    public function create(): string|RedirectResponse
    {
        if (($redirect = $this->guard('core.users')) !== null) {
            return $redirect;
        }

        if ($this->request->getMethod() === 'POST') {
            $email = trim((string) $this->request->getPost('email'));
            $username = trim((string) $this->request->getPost('username'));
            $password = (string) $this->request->getPost('password');

            $validation = service('validation');
            $validation->setRules([
                'email' => [
                    'label' => 'Email',
                    'rules' => 'required|valid_email|max_length[254]',
                ],
                'username' => [
                    'label' => 'Username',
                    'rules' => 'required|min_length[3]|max_length[30]|regex_match[/\A[a-zA-Z0-9\.]+\z/]',
                ],
                'password' => [
                    'label' => 'Password',
                    'rules' => 'required|min_length[8]',
                ],
            ]);

            if (! $validation->run(compact('email', 'username', 'password'))) {
                return redirect()->back()->withInput()->with('errors', $validation->getErrors());
            }

            $users = auth()->getProvider();

            if ($users->findByCredentials(['email' => $email]) !== null) {
                return redirect()->back()->withInput()->with('errors', [
                    'email' => 'A user with this email already exists.',
                ]);
            }

            $entity = new User([
                'email' => $email,
                'username' => $username,
                'password' => $password,
            ]);

            if (! $users->save($entity)) {
                return redirect()->back()->withInput()->with('errors', $users->errors());
            }

            $created = $users->findById($users->getInsertID());
            if (! $created instanceof User) {
                throw new RuntimeException('User was created but could not be reloaded.');
            }

            $users->addToDefaultGroup($created);
            $created->activate();
            Events::trigger(CoreEvents::USER_CREATED, $created);

            return redirect()->to('/admin/users')->with('message', 'User created.');
        }

        return view('admin/users/create', [
            'adminMenu' => $this->adminMenu(),
            'user'      => auth()->user(),
        ]);
    }

    public function access(int $id): string|RedirectResponse
    {
        if (($redirect = $this->guard('core.access')) !== null) {
            return $redirect;
        }

        $users = auth()->getProvider();
        $target = $users->withGroups()->withPermissions()->findById($id);

        if (! $target instanceof User) {
            return redirect()->to('/admin/users')->with('error', 'User not found.');
        }

        if ($this->request->getMethod() === 'POST') {
            try {
                service('mcmsAccess')->syncUserAccess(
                    $target,
                    (array) $this->request->getPost('groups'),
                    (array) $this->request->getPost('permissions'),
                );

                return redirect()->to('/admin/users/' . $id . '/access')
                    ->with('message', 'User access updated.');
            } catch (\Throwable $e) {
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }
        }

        return view('admin/users/access', [
            'adminMenu'   => $this->adminMenu(),
            'user'        => auth()->user(),
            'target'      => $target,
            'groups'      => service('mcmsAccess')->groups(),
            'permissions' => service('mcmsAccess')->permissions(),
        ]);
    }
}
