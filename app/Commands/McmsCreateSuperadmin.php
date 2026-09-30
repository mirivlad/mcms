<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
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
        $siteName = trim((string) (getenv('MCMS_SITE_NAME') ?: 'mCMS'));

        if ($email === '' || $password === '') {
            throw new RuntimeException(
                'Set MCMS_ADMIN_EMAIL and MCMS_ADMIN_PASSWORD before running mcms:admin:create.'
            );
        }

        $user = service('mcmsInstaller')->createSuperadmin(
            $email,
            $username,
            $password,
            $siteName,
            true,
        );

        CLI::write('Super administrator created: ' . $user->email, 'green');
    }
}
