<?php

declare(strict_types=1);

use App\Controllers\Admin\BaseAdminController;
use CodeIgniter\Test\CIUnitTestCase;
use MCMS\Core\Admin\AdminRegistry;

final class AdminNavigationTest extends CIUnitTestCase
{
    public function testFilteredMenuHidesEmptyGroupsAndPreservesLegacyContributions(): void
    {
        $registry = new AdminRegistry();
        $registry->addMenu('pages', 'Pages', '/admin/pages', permission: 'pages.view', group: 'content');
        $registry->addMenu('shop', 'Shop', '/admin/shop', permission: 'shop.view', group: 'commerce');
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
