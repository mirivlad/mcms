<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Shield\Entities\User;
use MCMS\Core\Auth\UserAdministration;
use MCMS\Core\Auth\UserDirectory;

final class UserManagementTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\\Settings', 'CodeIgniter\\Shield'];

    private function owner(): User
    {
        return service('mcmsInstaller')->createSuperadmin('owner@example.test', 'owner.test', 'StrongTestPass2026!', locale: 'en');
    }

    private function member(string $name): User
    {
        $provider = auth()->getProvider();
        $provider->save(new User(['username' => $name, 'email' => $name . '@example.test', 'password' => 'TestPassword2026!']));
        $user = $provider->findById($provider->getInsertID());
        $provider->addToDefaultGroup($user);
        $user->activate();
        return $user;
    }

    public function testIndependentFieldFiltersAndPageSizes(): void
    {
        $this->owner();
        for ($i = 0; $i < 36; $i++) $this->member('sample' . $i);
        $dir = new UserDirectory($this->db);
        self::assertCount(10, $dir->page('', 1, 10)['ids']);
        self::assertSame(37, $dir->page('', 1, 10)['count']);
        self::assertSame(1, $dir->page('', 1, 50)['lastPage']);
        self::assertSame(25, $dir->page('', 1, 500)['perPage']);
        self::assertSame(1, $dir->page('', 1, 10, 'sample35')['count']);
        self::assertSame(1, $dir->page('', 1, 10, '', 'sample34@')['count']);
        self::assertSame(1, $dir->page('', 1, 10, '', '', 'superadmin')['count']);
    }

    public function testAdminSeesFiltersAndActions(): void
    {
        $owner = $this->owner();
        $this->member('visibleuser');
        $response = $this->actingAs($owner)->withSession()->get('/admin/users?per_page=10&username=visible');
        $response->assertOK();
        $html = (string) $response->response()->getBody();
        foreach (['name="email"', 'name="group"', 'Registered', 'Last activity', 'assets/admin/users.js', 'users-directory-table', 'visibleuser'] as $term) {
            self::assertStringContainsString($term, $html);
        }
        $response = $this->withSession()->get('/admin/users/2/edit');
        $response->assertOK();
        self::assertStringContainsString('Set password', (string) $response->response()->getBody());
    }

    public function testDeleteIsSoftAndSelfActionsAreProtected(): void
    {
        $owner = $this->owner();
        $member = $this->member('targetmember');
        $actions = new UserAdministration($this->db);
        $this->actingAs($owner)->withSession();
        try {
            $actions->bulk([(string) $owner->id], 'delete', $owner);
            self::fail('Self deletion must be rejected');
        } catch (InvalidArgumentException) {
            self::assertNotNull(auth()->getProvider()->findById($owner->id));
        }
        $actions->bulk([(string) $member->id], 'delete', $owner);
        self::assertNull(auth()->getProvider()->findById($member->id));
        self::assertNotNull($this->db->table('users')->where('id', $member->id)->get()->getRow('deleted_at'));
    }

    public function testChangesAndResetPasswordThroughShield(): void
    {
        $owner = $this->owner();
        $member = $this->member('memberedit');
        $this->actingAs($owner)->withSession();
        $actions = new UserAdministration($this->db);
        $actions->updateProfile($member, 'renamed', 'renamed@example.test', 'Full Name', '+123');
        $fresh = auth()->getProvider()->withIdentities()->findById($member->id);
        self::assertSame('renamed', $fresh->username);
        self::assertSame('renamed@example.test', $fresh->email);
        $actions->setPassword($fresh, $owner, 'DifferentPassword2026!');
        self::assertTrue(password_verify('DifferentPassword2026!', auth()->getProvider()->findById($member->id)->getPasswordHash()));
        $random = $actions->resetPassword(auth()->getProvider()->findById($member->id), $owner);
        self::assertGreaterThan(28, strlen($random));
        self::assertTrue(password_verify($random, auth()->getProvider()->findById($member->id)->getPasswordHash()));
    }

    public function testBlockedUserCannotKeepAnAuthenticatedSession(): void
    {
        $owner = $this->owner();
        $member = $this->member('blockedmember');
        (new UserAdministration($this->db))->bulk([(string) $member->id], 'deactivate', $owner);
        $blocked = auth()->getProvider()->findById($member->id);
        self::assertTrue($blocked->isBanned());
        $this->actingAs($blocked)->withSession();
        $response = $this->get('/account');
        $response->assertRedirect();
        self::assertFalse(auth('session')->loggedIn());
    }

    public function testBulkActivationDoesNotAllowMixedSelfSelection(): void
    {
        $owner = $this->owner();
        $member = $this->member('lockedmember');
        $actions = new UserAdministration($this->db);
        $actions->bulk([(string) $member->id], 'deactivate', $owner);
        self::assertSame('banned', (string) $this->db->table('users')->where('id', $member->id)->get()->getRow('status'));
        try {
            $actions->bulk([(string) $member->id, (string) $owner->id], 'activate', $owner);
            self::fail('Must reject owner in selection');
        } catch (InvalidArgumentException) {
            self::assertSame('banned', (string) $this->db->table('users')->where('id', $member->id)->get()->getRow('status'));
        }
        $actions->bulk([(string) $member->id], 'activate', $owner);
        self::assertNull($this->db->table('users')->where('id', $member->id)->get()->getRow('status'));
    }
}
