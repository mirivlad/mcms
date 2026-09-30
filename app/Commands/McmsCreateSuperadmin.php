<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;

final class McmsCreateSuperadmin extends BaseCommand
{
    protected $group = 'mCMS';
    protected $name = 'mcms:admin:create';
    protected $description = 'Create the first mCMS super administrator from environment variables.';

    public function run(array $params): void
    {
        $email = trim((string) getenv('MCMS_ADMIN_EMAIL'));
        $password = (string) getenv('MCMS_ADMIN_PASSWORD');
        $username = trim((string) (getenv('MCMS_ADMIN_USERNAME') ?: 'admin'));

        if ($email === '' || $password === '') {
            throw new RuntimeException(
                'Set MCMS_ADMIN_EMAIL and MCMS_ADMIN_PASSWORD before running mcms:admin:create.'
            );
        }

        $users = auth()->getProvider();

        foreach ($users->findAll() as $existing) {
            if ($existing->inGroup('superadmin')) {
                throw new RuntimeException('A super administrator already exists.');
            }
        }

        if ($users->findByCredentials(['email' => $email]) !== null) {
            throw new RuntimeException('A user with this email already exists.');
        }

        $user = new User([
            'username' => $username,
            'email'    => $email,
            'password' => $password,
        ]);

        if (! $users->save($user)) {
            throw new RuntimeException('Unable to create super administrator: ' . implode('; ', $users->errors()));
        }

        $created = $users->findById($users->getInsertID());
        if ($created === null) {
            throw new RuntimeException('Super administrator was created but could not be reloaded.');
        }

        $created->syncGroups('superadmin');

        CLI::write('Super administrator created: ' . $email, 'green');
    }
}
