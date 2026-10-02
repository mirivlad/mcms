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
        'CodeIgniter\\Shield',
    ];

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();
    }

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

    public function testCreateAndEditGroupsPreserveSuperadminMatrix(): void
    {
        $access = new AccessManager($this->db);
        $access->saveGroup('editors', 'Editors', 'Content team', ['core.admin', 'core.users', 'core.admin'], true);
        self::assertSame('Editors', $access->groups()['editors']['title']);
        self::assertSame(['core.admin', 'core.users'], $access->matrix()['editors']);
        $access->saveGroup('editors', 'Writers', 'Updated', ['core.admin']);
        self::assertSame(['title' => 'Writers', 'description' => 'Updated'], $access->groups()['editors']);
        self::assertSame(['core.admin'], $access->matrix()['editors']);

        $superadmin = $access->matrix()['superadmin'];
        $access->saveGroup('superadmin', 'System owner', '', []);
        self::assertSame($superadmin, $access->matrix()['superadmin']);
    }

    public function testCreationCannotOverwriteAnExistingGroup(): void
    {
        $access = new AccessManager($this->db);
        $before = $access->groups();
        try {
            $access->saveGroup('admin', 'Overwritten', '', [], true);
            self::fail('Duplicate creation must be rejected.');
        } catch (InvalidArgumentException $e) {
            self::assertSame(lang('MCMS.errors.groupExists'), $e->getMessage());
            self::assertSame($before, $access->groups());
        }
    }

    public function testEditingADeletedGroupCannotRecreateIt(): void
    {
        $access = new AccessManager($this->db);
        try {
            $access->saveGroup('gone', 'Gone', '', []);
            self::fail('Editing a nonexistent group must be rejected.');
        } catch (InvalidArgumentException) {
            self::assertArrayNotHasKey('gone', $access->groups());
            self::assertArrayNotHasKey('gone', $access->matrix());
        }
    }

    public function testDeletionRemovesOnlyThisGroupsMembershipsAndGrants(): void
    {
        $access = new AccessManager($this->db);
        $access->saveGroup('editors', 'Editors', '', ['core.admin'], true);
        $users = auth()->getProvider();
        $users->save(new \CodeIgniter\Shield\Entities\User(['username' => 'access-test', 'active' => 1]));
        $id = $users->getInsertID();
        $user = $users->findById($id);
        $user->syncGroups('user', 'editors');
        $user->syncPermissions('core.users');
        self::assertSame(1, (int) $access->groupMemberCounts()['editors']);
        $access->deleteGroup('editors');

        self::assertArrayNotHasKey('editors', $access->groups());
        self::assertArrayNotHasKey('editors', $access->matrix());
        self::assertArrayNotHasKey('editors', $access->groupMemberCounts());
        $fresh = $users->findById($id);
        self::assertSame(['user'], $fresh->getGroups());
        self::assertSame(['core.users'], $fresh->getPermissions());
        self::assertFalse($fresh->can('core.admin'));
        self::assertTrue($fresh->can('core.users'));

        $access->syncCoreDefinitions();
        self::assertArrayNotHasKey('editors', $access->groups());
        $access->saveGroup('editors', 'New editors', '', ['core.admin'], true);
        self::assertSame(['user'], $users->findById($id)->getGroups());
    }

    public function testSystemAndDefaultGroupsCannotBeDeleted(): void
    {
        $access = new AccessManager($this->db);
        $before = $access->groups();
        foreach (['superadmin', 'admin', 'user'] as $id) {
            try {
                $access->deleteGroup($id);
                self::fail('System group deletion must be rejected.');
            } catch (RuntimeException $e) {
                self::assertSame(lang('MCMS.errors.protectedGroup'), $e->getMessage());
            }
        }
        self::assertSame($before, $access->groups());

        $access->saveGroup('members', 'Members', '', [], true);
        $config = config('AuthGroups');
        $previous = $config->defaultGroup;
        $config->defaultGroup = 'members';
        try {
            self::assertContains('members', $access->protectedGroups());
            $this->expectException(RuntimeException::class);
            $access->deleteGroup('members');
        } finally {
            $config->defaultGroup = $previous;
        }
    }

    public function testUnknownGroupCannotBeDeleted(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new AccessManager($this->db))->deleteGroup('missing');
    }

    public function testFailedSettingsWriteRollsBackMembershipRemoval(): void
    {
        $access = new AccessManager($this->db);
        $access->saveGroup('editors', 'Editors', '', ['core.admin'], true);
        $users = auth()->getProvider();
        $users->save(new \CodeIgniter\Shield\Entities\User(['username' => 'rollback-test', 'active' => 1]));
        $id = $users->getInsertID();
        $users->findById($id)->syncGroups('editors');
        $before = $access->groups();
        $this->db->query('CREATE TRIGGER deny_settings_write BEFORE UPDATE ON ' . $this->db->prefixTable('settings')
            . " WHEN OLD.key = 'matrix' BEGIN SELECT RAISE(ABORT, 'simulated settings failure'); END");
        try {
            $access->deleteGroup('editors');
            self::fail('Failed settings write must be reported.');
        } catch (RuntimeException) {
            self::assertSame($before, $access->groups());
            self::assertSame(['core.admin'], $access->matrix()['editors']);
            self::assertSame(['editors'], $users->findById($id)->getGroups());
        } finally {
            $this->db->query('DROP TRIGGER deny_settings_write');
            $this->db->resetTransStatus();
        }
    }
}
