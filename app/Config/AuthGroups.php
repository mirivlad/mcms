<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

class AuthGroups extends ShieldAuthGroups
{
    public string $defaultGroup = 'user';

    public array $groups = [
        'superadmin' => [
            'title'       => 'Super Administrator',
            'description' => 'Full control over mCMS, including modules and system settings.',
        ],
        'admin' => [
            'title'       => 'Administrator',
            'description' => 'Administrative access without unrestricted system control.',
        ],
        'user' => [
            'title'       => 'User',
            'description' => 'Regular authenticated user.',
        ],
    ];

    public array $permissions = [
        'core.admin'    => 'Access the mCMS administration area.',
        'core.users'    => 'Manage user accounts.',
        'core.access'   => 'Manage groups, permissions and user access.',
        'core.modules'  => 'Install, enable, disable and update modules.',
        'core.settings' => 'Manage core and module settings.',
        'core.themes'   => 'Manage site themes and appearance.',
        'core.system'   => 'View diagnostics and perform system maintenance.',
        'core.backups'  => 'Create and download full site backups.',
    ];

    public array $matrix = [
        'superadmin' => [
            'core.*',
        ],
        'admin' => [
            'core.admin',
            'core.users',
            'core.settings',
            'core.themes',
        ],
        'user' => [],
    ];
}
