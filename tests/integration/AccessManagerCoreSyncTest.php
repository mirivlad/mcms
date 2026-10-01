<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MCMS\Core\Auth\AccessManager;

final class AccessManagerCoreSyncTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = [
        'App',
        'CodeIgniter\\Settings',
    ];

    public function testCorePermissionSyncPreservesUserMatrix(): void
    {
        $settings = service('settings');
        $settings->set('AuthGroups.groups', [
            'admin' => [
                'title' => 'Custom admin',
                'description' => 'Customized',
            ],
        ]);
        $settings->set('AuthGroups.permissions', [
            'legacy.permission' => 'Legacy permission',
        ]);
        $settings->set('AuthGroups.matrix', [
            'superadmin' => ['legacy.permission'],
            'admin' => ['core.admin'],
        ]);

        (new AccessManager($this->db))->syncCoreDefinitions();

        $groups = (array) $settings->get('AuthGroups.groups');
        $permissions = (array) $settings->get('AuthGroups.permissions');
        $matrix = (array) $settings->get('AuthGroups.matrix');

        self::assertSame('Custom admin', $groups['admin']['title']);
        self::assertArrayHasKey('superadmin', $groups);
        self::assertArrayHasKey('user', $groups);

        self::assertArrayHasKey('legacy.permission', $permissions);
        self::assertArrayHasKey('core.themes', $permissions);

        self::assertSame(['core.admin'], $matrix['admin']);
        self::assertContains('legacy.permission', $matrix['superadmin']);
        self::assertContains('core.*', $matrix['superadmin']);
    }
}
