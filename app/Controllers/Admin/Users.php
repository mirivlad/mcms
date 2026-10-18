<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Entities\User;
use MCMS\Core\Events\CoreEvents;
use MCMS\Core\Auth\UserDirectory;
use MCMS\Core\Auth\UserAdministration;
use RuntimeException;

final class Users extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if (($redirect = $this->guard('core.users')) !== null) {
            return $redirect;
        }

        $search = $this->request->getGet('q');
        $directory = (new UserDirectory(db_connect()))->page(
            is_string($search) ? $search : '',
            filter_var($this->request->getGet('page'), FILTER_VALIDATE_INT) ?: 1,
            filter_var($this->request->getGet('per_page'), FILTER_VALIDATE_INT) ?: 25,
            $this->filter('username'),
            $this->filter('email'),
            $this->filter('group')
        );

        // Shield owns identities, groups and direct permission hydration.
        $users = [];
        if ($directory['ids'] !== []) {
            $provider = auth()->getProvider();
            $found = $provider->withIdentities()->withGroups()->withPermissions()->find($directory['ids']);
            $byId = [];
            foreach ($found as $entry) {
                $byId[(int) $entry->id] = $entry;
            }
            foreach ($directory['ids'] as $id) {
                if (isset($byId[$id])) {
                    $users[] = $byId[$id];
                }
            }
        }

        return view('admin/users/index', [
            'adminMenu' => $this->adminMenu(),
            'users'     => $users,
            'user'      => auth()->user(),
            'canAccess' => $this->can('core.access'),
            'canSensitive' => $this->can('core.access'),
            'groupOptions' => array_keys(service('mcmsAccess')->groups()),
            ...$directory,
        ]);
    }

    private function filter(string $name): string
    {
        $value = $this->request->getGet($name);
        return is_string($value) ? $value : '';
    }

    /** Edit profile data without touching groups/permissions. */
    public function edit(int $id): string|RedirectResponse
    {
        if (($redirect = $this->guard('core.users')) !== null) return $redirect;
        $target = auth()->getProvider()->withIdentities()->withGroups()->findById($id);
        if (! $target instanceof User) return redirect()->to('/admin/users')->with('error', lang('MCMS.errors.userNotFound'));
        $service = new UserAdministration(db_connect());
        if (! $service->mayManage($target, auth()->user())) {
            return redirect()->to('/admin/users')->with('error', lang('MCMS.users.protected'));
        }
        if ($this->request->getMethod() === 'POST') {
            try {
                $service->updateProfile($target, trim((string) $this->request->getPost('username')),
                    trim((string) $this->request->getPost('email')),
                    trim((string) $this->request->getPost('full_name')),
                    trim((string) $this->request->getPost('phone')));
                return redirect()->to('/admin/users/' . $id . '/edit')->with('message', lang('MCMS.users.saved'));
            } catch (\Throwable $e) {
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }
        }
        $profile = db_connect()->table('account_profiles')->where('user_id', $id)->get()->getRowArray() ?? [];
        service('response')->setHeader('Cache-Control', 'private, no-store');
        return view('admin/users/edit', [
            'adminMenu' => $this->adminMenu(), 'user' => auth()->user(),
            'target' => $target, 'profile' => $profile,
            'canSensitive' => $this->can('core.access') && (int) auth()->id() !== $id,
        ]);
    }

    public function action(int $id): RedirectResponse
    {
        if (($redirect = $this->guard('core.access')) !== null) return $redirect;
        $target = auth()->getProvider()->withIdentities()->withGroups()->findById($id);
        if (! $target instanceof User) return redirect()->to('/admin/users')->with('error', lang('MCMS.errors.userNotFound'));
        try {
            $admin = new UserAdministration(db_connect());
            $action = (string) $this->request->getPost('action');
            if ($action === 'password') {
                $new = (string) $this->request->getPost('password');
                if ($new !== (string) $this->request->getPost('password_confirm')) {
                    throw new \InvalidArgumentException(lang('MCMS.users.passwordMismatch'));
                }
                $admin->setPassword($target, auth()->user(), $new);
                return redirect()->to('/admin/users/' . $id . '/edit')->with('message', lang('MCMS.users.passwordSaved'));
            }
            if ($action === 'reset') {
                $new = $admin->resetPassword($target, auth()->user());
                return redirect()->to('/admin/users/' . $id . '/edit')->with('temporaryPassword', $new);
            }
            $admin->bulk([$id], $action, auth()->user());
            return redirect()->to('/admin/users')->with('message', lang('MCMS.users.actionDone'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function bulk(): RedirectResponse
    {
        if (($redirect = $this->guard('core.access')) !== null) return $redirect;
        try {
            $ids = $this->request->getPost('ids');
            (new UserAdministration(db_connect()))->bulk(
                is_array($ids) ? $ids : [], (string) $this->request->getPost('action'), auth()->user());
            return redirect()->to('/admin/users')->with('message', lang('MCMS.users.actionDone'));
        } catch (\Throwable $e) {
            return redirect()->to('/admin/users')->with('error', $e->getMessage());
        }
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
            ]);

            if (! $validation->run(compact('email', 'username', 'password'))) {
                return redirect()->back()->withInput()->with('errors', $validation->getErrors());
            }

            $users = auth()->getProvider();

            if ($users->findByCredentials(['email' => $email]) !== null) {
                return redirect()->back()->withInput()->with('errors', [
                    'email' => lang('MCMS.errors.userExists'),
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

            return redirect()->to('/admin/users')->with('message', lang('MCMS.messages.userCreated'));
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
            return redirect()->to('/admin/users')->with('error', lang('MCMS.errors.userNotFound'));
        }
        if (! (new UserAdministration(db_connect()))->mayManage($target, auth()->user())) {
            return redirect()->to('/admin/users')->with('error', lang('MCMS.users.protected'));
        }

        if ($this->request->getMethod() === 'POST') {
            try {
                service('mcmsAccess')->syncUserAccess(
                    $target,
                    (array) $this->request->getPost('groups'),
                    (array) $this->request->getPost('permissions'),
                );

                return redirect()->to('/admin/users/' . $id . '/access')
                    ->with('message', lang('MCMS.messages.userAccessUpdated'));
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
