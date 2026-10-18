<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MCMS\Core\Auth\UserDirectory;

final class UserDirectoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use \CodeIgniter\Test\FeatureTestTrait;
    use \CodeIgniter\Shield\Test\AuthenticationTesting;

    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\Settings', 'CodeIgniter\Shield'];

    public function testPaginationDoesNotDropUsersAfterFirstHundredAndClampsPages(): void
    {
        $this->seedUsers(105);
        $directory = new UserDirectory($this->db);

        $first = $directory->page('', 1);
        self::assertSame(105, $first['count']);
        self::assertSame(5, $first['lastPage']);
        self::assertCount(25, $first['ids']);
        $sorted = $first['ids'];
        rsort($sorted);
        self::assertSame($sorted, $first['ids']);

        $last = $directory->page('', 999);
        self::assertSame(5, $last['page']);
        self::assertCount(5, $last['ids']);
        self::assertSame([], array_intersect($first['ids'], $last['ids']));

        $second = $directory->page('', 2);
        self::assertCount(25, $second['ids']);
        self::assertLessThan(min($first['ids']), max($second['ids']));
        self::assertSame(1, $directory->page('', -4)['page']);
    }

    public function testSearchMatchesUsernameAndEmailWithoutWildcardExpansionAndExcludesDeletedUsers(): void
    {
        $this->seedUsers(3);
        $firstId = (int) $this->db->table('users')->where('username', 'member000')->get()->getRow('id');
        $this->db->table('auth_identities')->insert([
            'user_id' => $firstId, 'type' => 'email_password',
            'secret' => 'distinctive@example.test', 'secret2' => password_hash('temporary-pass', PASSWORD_DEFAULT),
        ]);
        $this->db->table('users')->where('username', 'member002')
            ->update(['deleted_at' => '2026-10-18 00:00:00']);
        $directory = new UserDirectory($this->db);

        self::assertSame([$firstId], $directory->page('distinctive@example.test')['ids']);
        self::assertSame([$firstId], $directory->page('member000')['ids']);
        self::assertSame(2, $directory->page('')['count']);
        self::assertSame([], $directory->page('%')['ids']);
        self::assertSame([], $directory->page('no-such-user')['ids']);
        self::assertSame(1, $directory->page('no-such-user', 8)['page']);
    }

    public function testAdministratorCanPageAndFilterTheDirectory(): void
    {
        $admin = service('mcmsInstaller')->createSuperadmin(
            'users-admin@example.test', 'users.admin', 'Admin-Test-2026!', locale: 'en'
        );
        $this->seedUsers(35);

        $first = $this->actingAs($admin)->withSession()->get('/admin/users');
        $first->assertOK();
        $html = (string) $first->response()->getBody();
        self::assertStringContainsString('member034', $html);
        self::assertStringNotContainsString('<div class="fw-semibold">member000</div>', $html);
        self::assertStringContainsString('page=2', html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        self::assertStringContainsString('name="q"', $html);
        self::assertStringContainsString('users-directory-table', $html);
        self::assertStringNotContainsString('class="table-responsive"', $html);
        self::assertStringContainsString('id="users-select-all"', $html);

        $last = $this->withSession()->get('/admin/users?page=2');
        $last->assertOK();
        self::assertStringContainsString('<div class="fw-semibold">member000</div>', (string) $last->response()->getBody());

        $filtered = $this->withSession()->get('/admin/users?q=member011');
        $filtered->assertOK();
        $filteredHtml = (string) $filtered->response()->getBody();
        self::assertStringContainsString('<div class="fw-semibold">member011</div>', $filteredHtml);
        self::assertStringNotContainsString('<div class="fw-semibold">member012</div>', $filteredHtml);
    }

    private function seedUsers(int $count): void
    {
        $records = [];
        for ($i = 0; $i < $count; $i++) {
            $records[] = [
                'username' => sprintf('member%03d', $i),
                'active' => 1,
                'created_at' => '2026-10-18 00:00:00',
            ];
        }
        $this->db->table('users')->insertBatch($records);
    }
}
