<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use MCMS\Core\Themes\ThemeManager;
use MCMS\Core\Themes\ThemePackages;

/**
 * Check the shipped third-party ZIP, not a built-in theme or copied source.
 */
#[RunTestsInSeparateProcesses]
final class AtelierPublicAccountThemeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\Settings', 'CodeIgniter\Shield'];
    private string $packageRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->packageRoot = sys_get_temp_dir() . '/mcms-atelier-accounts-' . bin2hex(random_bytes(6));
        mkdir($this->packageRoot);
        $manager = $this->manager();
        $packages = new ThemePackages($manager, $this->packageRoot . '/packages');
        $sourceZip = ROOTPATH . 'examples/packages/mcms-atelier-1.1.0.zip';
        $preview = $packages->prepare($sourceZip);
        $installed = $packages->install($preview['token'], $preview['sha256']);
        self::assertTrue($installed->external);
        $active = $this->manager();
        $active->activate('mcms-atelier');
        \Config\Services::injectMock('mcmsThemes', $active);
        service('settings')->set('Auth.allowRegistration', true);
    }

    protected function tearDown(): void
    {
        \Config\Services::resetSingle('mcmsThemes');
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->packageRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $entry) {
            $entry->isDir() && ! $entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->packageRoot);
        parent::tearDown();
    }

    private function manager(): ThemeManager
    {
        return new ThemeManager(
            service('settings'),
            ROOTPATH . 'themes',
            packagesPath: $this->packageRoot . '/packages'
        );
    }

    public function testGuestLoginAndRegisterUseInstalledAtelierHeaderAndFooter(): void
    {
        foreach (['login', 'register'] as $path) {
            $response = $this->get('/' . $path);
            $response->assertOK();
            $html = (string) $response->response()->getBody();
            self::assertStringContainsString('data-theme="mcms-atelier"', $html);
            self::assertStringContainsString('mCMS', $html);
            self::assertStringContainsString('site-header-inner', $html);
            self::assertStringContainsString('site-footer', $html);
            self::assertStringContainsString('mcms-account-card', $html);
            self::assertStringContainsString('assets/css/account-nav.css', $html);
            self::assertStringContainsString('/login', $html);
            self::assertStringContainsString('/register', $html);
            self::assertStringContainsString('theme-assets/mcms-atelier/1.1.0/', html_entity_decode($html));
        }
    }

    public function testOrderDetailsAndCoreAccountFallbackRenderInsideInstalledTheme(): void
    {
        $html = service('mcmsThemes')->render('account.order', [
            'accountPageTitle' => lang('MCMS.account.order'),
            'metaTitle' => 'Test order',
            'metaRobots' => 'noindex, nofollow',
            'order' => [
                'id' => 123,
                'status' => 'new',
                'created_at' => '2026-10-13 10:00:00',
                'total_minor' => 2500,
                'currency' => 'RUB',
            ],
            'items' => [[
                'product_title' => 'Atelier sample',
                'variant_label' => '',
                'quantity' => 1,
                'subtotal_minor' => 2500,
                'currency' => 'RUB',
            ]],
        ], 'auth/order');
        self::assertStringContainsString('data-theme="mcms-atelier"', $html);
        self::assertStringContainsString('site-footer', $html);
        self::assertStringContainsString('Atelier sample', $html);
        self::assertStringContainsString('25.00', $html);
    }

    public function testSignedInVisitorGetsAccountLinksAndThemedProfileAndOrders(): void
    {
        $provider = auth()->getProvider();
        $user = $provider->createNewUser([
            'username' => 'atelierreader',
            'email' => 'atelierreader@example.test',
            'password' => 'AtelierReaderPassword2026!',
        ]);
        self::assertTrue($provider->save($user));
        $user = $provider->findById($provider->getInsertID());
        $provider->addToDefaultGroup($user);
        $user->activate();
        auth('session')->getAuthenticator()->login($user);

        foreach (['account', 'account/orders'] as $path) {
            $response = $this->get('/' . $path);
            $response->assertOK();
            $html = (string) $response->response()->getBody();
            self::assertStringContainsString('data-theme="mcms-atelier"', $html);
            self::assertStringContainsString('site-footer', $html);
            if ($path === 'account') {
                self::assertStringContainsString('mcms-account-dashboard-shell', $html);
                self::assertStringContainsString('mcms-account-form-grid', $html);
                self::assertStringNotContainsString('<div class="mcms-account-card">', $html);
                self::assertStringContainsString('name="current_password"', $html);
            } else {
                self::assertStringContainsString('mcms-account-card', $html);
            }
            self::assertStringContainsString('/account', $html);
            self::assertStringContainsString('/logout', $html);
            self::assertStringNotContainsString('site standalone', $html);
        }
    }
}
