<?php

declare(strict_types=1);

namespace MCMS\Core\Install;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;

final class InstallationService
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function isInstalled(): bool
    {
        if (! $this->db->tableExists('auth_groups_users')) {
            return false;
        }

        return $this->db->table('auth_groups_users')
            ->where('group', 'superadmin')
            ->countAllResults() > 0;
    }

    public function createSuperadmin(
        string $email,
        string $username,
        string $password,
        string $siteName = 'mCMS',
        bool $allowRegistration = true,
    ): User {
        if ($this->isInstalled()) {
            throw new RuntimeException('mCMS is already installed.');
        }

        $email = trim($email);
        $username = trim($username);
        $siteName = trim($siteName);

        if ($email === '' || $username === '' || $password === '') {
            throw new RuntimeException('Email, username and password are required.');
        }

        if ($siteName === '') {
            throw new RuntimeException('Site name is required.');
        }

        $users = auth()->getProvider();

        if ($users->findByCredentials(['email' => $email]) !== null) {
            throw new RuntimeException('A user with this email already exists.');
        }

        $user = new User([
            'username' => $username,
            'email'    => $email,
            'password' => $password,
        ]);

        if (! $users->save($user)) {
            throw new RuntimeException(
                'Unable to create super administrator: ' . implode('; ', $users->errors())
            );
        }

        $created = $users->findById($users->getInsertID());
        if (! $created instanceof User) {
            throw new RuntimeException('Super administrator was created but could not be reloaded.');
        }

        $created->syncGroups('superadmin');

        $settings = service('settings');
        $settings->set('Core.siteName', $siteName);
        $settings->set('Auth.allowRegistration', $allowRegistration);

        return $created;
    }
}
