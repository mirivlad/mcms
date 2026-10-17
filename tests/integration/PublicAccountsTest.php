<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Auth as AuthConfig;
use Config\AuthGroups;

final class PublicAccountsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\\Settings', 'CodeIgniter\\Shield'];

    protected function tearDown(): void
    {
        $this->migrations->setNamespace('MCMS\\Modules\\Captcha')->regress(0, 'tests');
        parent::tearDown();
    }

    public function testShieldUsesOnePublicAccountWithNoAdministrativePermission(): void
    {
        $config = config('Auth');
        self::assertInstanceOf(AuthConfig::class, $config);
        self::assertSame('/account', $config->redirects['login']);
        self::assertSame('/account', $config->redirects['register']);
        self::assertSame('auth/login', $config->views['login']);
        self::assertSame('auth/register', $config->views['register']);
        $groups = config(AuthGroups::class);
        self::assertSame('user', $groups->defaultGroup);
        self::assertSame([], $groups->matrix['user']);
        self::assertTrue($this->db->tableExists('account_profiles'));
    }

    public function testPublicLoginRegisterRoutesAndAccountRequireAuthentication(): void
    {
        service('settings')->set('Auth.allowRegistration', true);
        $login = $this->get('/login');
        $login->assertOK();
        self::assertStringContainsString('name="email"', $login->response()->getBody());

        $register = $this->get('/register');
        $register->assertOK();
        self::assertStringContainsString('enable the Captcha module', (string) $register->response()->getBody());
        self::assertStringContainsString('name="username"', file_get_contents(APPPATH . 'Views/auth/register.php'));

        $account = $this->get('/account');
        self::assertTrue($account->isRedirect());
    }

    public function testEveryBundledThemeWrapsLoginAndRegistrationInSiteLayout(): void
    {
        service('settings')->set('Auth.allowRegistration', true);

        foreach (['mcms-default', 'mcms-depth', 'mcms-focus', 'mcms-signature'] as $theme) {
            service('settings')->set('Core.theme', $theme);

            foreach (['login', 'register'] as $path) {
                $response = $this->get('/' . $path);
                $response->assertOK();
                $html = (string) $response->response()->getBody();

                self::assertStringContainsString('data-theme="' . $theme . '"', $html, $path);
                self::assertStringContainsString('<header', $html, $path);
                self::assertStringContainsString('<footer', $html, $path);
                self::assertStringContainsString('mcms-account-card', $html, $path);
                self::assertStringContainsString('name="email"', $path === 'login' ? $html : file_get_contents(APPPATH . 'Views/auth/register.php'));
                self::assertStringNotContainsString('background: #f5f7fb', $html);
            }
        }
    }

    public function testRegistrationWithoutCaptchaDoesNotCreateAUser(): void
    {
        service('settings')->set('Auth.allowRegistration', true);
        $before = $this->db->table('users')->countAllResults();
        $response = $this->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])->post('/register', [
            'email' => 'unverified@example.test',
            'username' => 'unverified',
            'password' => 'CorrectHorseBattery36!',
            'password_confirm' => 'CorrectHorseBattery36!',
        ]);
        self::assertTrue($response->isRedirect());
        self::assertSame($before, $this->db->table('users')->countAllResults());
    }

    public function testBuiltInThemesExposePublicAccountEntryPoints(): void
    {
        foreach (['mcms-default', 'mcms-signature', 'mcms-depth', 'mcms-focus'] as $theme) {
            $layout = file_get_contents(ROOTPATH . 'themes/' . $theme . '/Views/layout.php');
            self::assertStringContainsString("view('auth/nav')", $layout, $theme);
        }
    }

    public function testOrderOwnershipPredicateIsRequiredForPublicOrderDetails(): void
    {
        $source = file_get_contents(APPPATH . 'Controllers/Account.php');
        self::assertStringContainsString("->where('customer_user_id', (int) auth()->id())", $source);
        self::assertStringContainsString("->where('id', " . '$id' . ")", $source);
    }

    public function testCaptchaRegistrationCreatesOnlyOrdinaryUser(): void
    {
        service('settings')->set('Auth.allowRegistration', true);
        $services = new \MCMS\Core\Modules\ModuleServiceRegistry();
        $manager = new \MCMS\Core\Modules\ModuleManager(
            $this->db,
            new \MCMS\Core\Admin\AdminRegistry(),
            $services,
            ROOTPATH . 'modules'
        );
        $manager->enable('captcha');
        $manager->bootEnabled();
        \Config\Services::injectMock('mcmsModuleServices', $services);
        session_id('mcms-register-flow');

        $page = $this->get('/register');
        $page->assertOK();
        $html = (string) $page->response()->getBody();
        self::assertMatchesRegularExpression('/name="captcha_token"/', $html);
        self::assertSame(1, preg_match('/name="captcha_token" value="([a-f0-9]{48})"/', $html, $matches));
        $record = $this->db->table('captcha_challenges')
            ->where('token_hash', hash('sha256', $matches[1]))
            ->get()->getRowArray();
        self::assertNotNull($record);
        $answer = null;
        // Easy CAPTCHA now produces answers through 9 (and other policies use
        // different ranges). Do not make registration depend on a random 2..5.
        for ($n = 0; $n <= 20; $n++) {
            if (hash_equals($record['answer_hash'], hash('sha256', $record['salt'] . ':' . $n))) {
                $answer = (string) $n;
                break;
            }
        }
        self::assertNotNull($answer);
        $post = $this->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])->post('/register', [
            'email' => 'visitor@example.test',
            'username' => 'visitor01',
            'password' => 'aLongStrongPassword2026!',
            'password_confirm' => 'aLongStrongPassword2026!',
            'captcha_token' => $matches[1],
            'captcha_answer' => $answer,
        ]);
        $post->assertRedirect();
        self::assertSame(1, $this->db->table('users')->where('username', 'visitor01')->countAllResults());
        self::assertSame(1, $this->db->table('auth_groups_users')
            ->where('group', 'user')->countAllResults());
        $account = $this->get('/account');
        $account->assertOK();
        self::assertStringContainsString('visitor01', (string) $account->response()->getBody());
        $admin = $this->get('/admin');
        $admin->assertRedirect();
        $orders = $this->get('/account/orders');
        $orders->assertOK();
        self::assertStringContainsString('mcms-account-card', (string) $orders->response()->getBody());
        $updated = $this->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])->post('/account/profile', [
            'username' => 'visitor02',
            'full_name' => 'Visitor Two',
            'phone' => '+7 100 200',
        ]);
        $updated->assertRedirect();
        $stored = $this->db->table('account_profiles')->get()->getRowArray();
        self::assertSame('Visitor Two', $stored['full_name']);
        self::assertSame('+7 100 200', $stored['phone']);
        self::assertSame(1, $this->db->table('users')->where('username', 'visitor02')->countAllResults());
    }
}
