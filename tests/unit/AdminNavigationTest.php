<?php

declare(strict_types=1);

use App\Controllers\Admin\BaseAdminController;
use CodeIgniter\Test\CIUnitTestCase;
use MCMS\Core\Admin\AdminRegistry;

final class AdminNavigationTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $auth = $this->createMock(\CodeIgniter\Shield\Auth::class);
        $auth->method('setAuthenticator')->willReturnSelf();
        $auth->method('user')->willReturn(null);
        \Config\Services::injectMock('auth', $auth);
    }

    public function testFilteredMenuHidesEmptyGroupsAndPreservesLegacyContributions(): void
    {
        $registry = new AdminRegistry();
        $registry->addMenu('pages', 'Pages', '/admin/pages', permission: 'pages.view', group: 'content');
        $registry->addMenu('shop', 'Shop', '/admin/shop', permission: 'shop.view', group: 'commerce');
        $registry->addMenu('analytics', 'Analytics', '/admin/analytics', permission: 'analytics.view', group: 'main', overviewSection: 'maintenance');
        $registry->addMenu('legacy', 'Legacy', '/admin/legacy');
        $registry->addMenu('custom', 'Custom', '/admin/custom', group: 'unknown');
        \Config\Services::injectMock('mcmsAdmin', $registry);
        $controller = new class extends BaseAdminController {
            protected function can(string $permission): bool
            {
                return $permission === 'pages.view';
            }

            public function menu(): array
            {
                return $this->adminMenu();
            }
        };

        $groups = $registry->groupedMenu($controller->menu());
        self::assertSame(['content', 'extensions'], array_column($groups, 'id'));
        self::assertSame(['pages'], array_column($groups[0]['items'], 'id'));
        self::assertSame(['legacy', 'custom'], array_column($groups[1]['items'], 'id'));
    }

    public function testAdministrationSectionsRespectPermissionsAndKeepUnclassifiedItems(): void
    {
        $registry = new AdminRegistry();
        $registry->addMenu('users', 'Users', '/admin/users', permission: 'core.users', group: 'administration', section: 'access');
        $registry->addMenu('comments', 'Comments', '/admin/comments', permission: 'comments.view', group: 'administration', section: 'moderation');
        $registry->addMenu('backups', 'Backups', '/admin/backups', permission: 'core.backups', group: 'administration', section: 'maintenance');
        $registry->addMenu('legacy', 'Legacy', '/admin/legacy', group: 'administration');
        $registry->addMenu('custom', 'Custom', '/admin/custom', group: 'administration', section: 'unknown');
        \Config\Services::injectMock('mcmsAdmin', $registry);
        $controller = new class extends BaseAdminController {
            protected function can(string $permission): bool
            {
                return $permission === 'comments.view';
            }

            public function menu(): array
            {
                return $this->adminMenu();
            }
        };
        $menu = $controller->menu();
        $groups = $registry->groupedMenu($menu);
        self::assertSame(['moderation', 'other'], array_column($groups[0]['sections'], 'id'));
        self::assertSame(['legacy', 'custom'], array_column($groups[0]['sections'][1]['items'], 'id'));
        self::assertSame($menu, $groups[0]['items']);

        $html = view('admin/dashboard', ['adminMenu' => $menu, 'user' => (object) ['username' => 'Moderator']]);
        $document = new DOMDocument();
        @$document->loadHTML($html);
        $xpath = new DOMXPath($document);
        self::assertSame(1, $xpath->query('//div[contains(@class, "page-wrapper")]//a[contains(@href, "/admin/comments")]')->length);
        self::assertSame(0, $xpath->query('//a[contains(@href, "/admin/users") or contains(@href, "/admin/backups")]')->length);
        self::assertSame(0, $xpath->query('//details[@id="admin-menu-administration-access"]')->length);
        self::assertSame(1, $xpath->query('//details[@id="admin-menu-administration-moderation"]')->length);
    }

    public function testNestedAdministrationRouteOpensItsSectionAndOverviewHasQuickLinks(): void
    {
        $request = service('request');
        $path = $request->getUri()->getPath();
        try {
            $request->getUri()->setPath('/admin/users/42/edit');
            $registry = new AdminRegistry();
            $registry->addMenu('dashboard', 'Overview', '/admin', group: 'main');
            $registry->addMenu('users', 'Users', '/admin/users', group: 'administration', section: 'access');
            $registry->addMenu('settings', 'Settings', '/admin/settings', group: 'administration', section: 'configuration');
            \Config\Services::injectMock('mcmsAdmin', $registry);
            $html = view('admin/dashboard', ['adminMenu' => $registry->menu(), 'user' => (object) ['username' => 'Owner']]);
            $document = new DOMDocument();
            @$document->loadHTML($html);
            $xpath = new DOMXPath($document);
            self::assertSame(1, $xpath->query('//details[@id="admin-menu-administration"][@open]')->length);
            self::assertSame(1, $xpath->query('//details[@id="admin-menu-administration-access"][@open]')->length);
            self::assertSame(0, $xpath->query('//details[@id="admin-menu-administration-configuration"][@open]')->length);
            self::assertSame(1, $xpath->query('//a[@aria-current="page"]')->length);
            self::assertSame(site_url('admin/users'), $xpath->query('//a[@aria-current="page"]')->item(0)->getAttribute('href'));
            self::assertSame(2, $xpath->query('//div[contains(@class, "page-wrapper")]//a[contains(@class, "list-group-item")]')->length);
            self::assertStringContainsString(lang('MCMS.dashboard.description'), $html);
            self::assertStringNotContainsString(lang('MCMS.dashboard.running'), $html);
        } finally {
            $request->getUri()->setPath($path);
        }
    }

    public function testOverviewCanGroupAnalyticsUnderMaintenanceWithoutMovingTheSidebarLink(): void
    {
        $registry = new AdminRegistry();
        $registry->addMenu('dashboard', 'Overview', '/admin', group: 'main');
        $registry->addMenu('analytics', 'Analytics', '/admin/analytics', group: 'main', overviewSection: 'maintenance');
        $registry->addMenu('backups', 'Backups', '/admin/backups', group: 'administration', section: 'maintenance');
        \Config\Services::injectMock('mcmsAdmin', $registry);
        $menu = $registry->menu();
        $html = view('admin/dashboard', ['adminMenu' => $menu, 'user' => (object) ['username' => 'Owner']]);
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $xpath = new DOMXPath($document);
        $links = $xpath->query('//div[contains(@class, "page-wrapper")]//a[contains(@href, "/admin/analytics")]');
        self::assertSame(1, $links->length);
        self::assertSame(lang('MCMS.nav.sectionMaintenance'),
            $xpath->evaluate('string(../../div[contains(@class, "card-header")]/h3)', $links->item(0)));
        self::assertSame(1, $xpath->query('//div[contains(@class, "page-wrapper")]//div[contains(@class, "card-header")]/h3')->length);
        self::assertSame(1, $xpath->query('//aside//a[contains(@href, "/admin/analytics")]')->length);
        self::assertSame(0, $xpath->query('//aside//details//a[contains(@href, "/admin/analytics")]')->length);
        self::assertSame($menu, $registry->menu());
    }

    public function testNestedPageOpensItsGroupAndPublicLinkRespectsBasePath(): void
    {
        $app = config('App');
        $baseURL = $app->baseURL;
        $request = service('request');
        $path = $request->getUri()->getPath();
        $locale = $request->getLocale();
        $languageLocale = service('language')->getLocale();
        try {
            $app->baseURL = 'http://example.com/cms/';
            $request->setLocale('ru');
            service('language')->setLocale('ru');
            $request->getUri()->setPath('/cms/admin/pages/42/edit');
            $registry = new AdminRegistry();
            $registry->addMenu('dashboard', 'Dashboard', '/admin', group: 'main');
            $registry->addMenu('pages', 'Pages', '/admin/pages', group: 'content');
            $registry->addMenu('settings', 'Settings', '/admin/settings', group: 'administration');
            $html = view('admin/dashboard', ['adminMenu' => $registry->menu(), 'user' => (object) ['username' => 'Editor']]);
            $document = new DOMDocument();
            @$document->loadHTML('<?xml encoding="utf-8" ?>' . $html);
            $xpath = new DOMXPath($document);
            self::assertSame(1, $xpath->query('//details[@id="admin-menu-content"][@open]')->length);
            self::assertSame(0, $xpath->query('//details[@id="admin-menu-administration"][@open]')->length);
            self::assertSame(0, $xpath->query('//details[@id="admin-menu-commerce"]')->length);
            self::assertSame('http://example.com/cms/admin/pages', $xpath->query('//a[@aria-current="page"]')->item(0)->getAttribute('href'));
            self::assertSame(1, $xpath->query('//a[@aria-current="page"]')->length);
            $site = $xpath->query('//a[contains(@class, "admin-site-link")]')->item(0);
            self::assertSame('http://example.com/cms/', $site->getAttribute('href'));
            self::assertSame('_blank', $site->getAttribute('target'));
            self::assertSame('noopener noreferrer', $site->getAttribute('rel'));
            self::assertStringContainsString('Открыть сайт', $site->textContent);
        } finally {
            $app->baseURL = $baseURL;
            $request->getUri()->setPath($path);
            $request->setLocale($locale);
            service('language')->setLocale($languageLocale);
        }
    }
}
