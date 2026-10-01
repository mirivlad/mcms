<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MCMS\Core\Themes\ThemeManager;

final class DefaultThemeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = [
        'App',
        'CodeIgniter\\Settings',
    ];

    public function testDefaultThemeDeclaresLocationsAndRendersHomeAndPage(): void
    {
        service('language')->setLocale('en');

        $manager = new ThemeManager(service('settings'), ROOTPATH . 'themes');
        $theme = $manager->ensureActive();

        self::assertNotNull($theme);
        self::assertSame('default', $theme->id);
        self::assertArrayHasKey('header.primary', $theme->menuLocations);
        self::assertSame(2, $theme->menuLocations['header.primary']['max_depth']);
        self::assertArrayHasKey('footer.primary', $theme->menuLocations);
        self::assertSame(1, $theme->menuLocations['footer.primary']['max_depth']);

        $home = $manager->render('home');
        self::assertStringContainsString('mCMS Default', $manager->displayName($theme));
        self::assertStringContainsString('/assets/themes/default/css/theme.css', $home);
        self::assertStringContainsString('mcms-site-header', $home);

        $page = $manager->render('page', [
            'page' => [
                'title' => 'Theme page',
                'body' => '<p>Theme body</p>',
                'seo_title' => 'Theme SEO',
                'seo_description' => 'Theme description',
            ],
        ]);

        $decodedPage = html_entity_decode($page, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        self::assertStringContainsString('Theme SEO', $decodedPage);
        self::assertStringContainsString('Theme description', $decodedPage);
        self::assertStringContainsString('<p>Theme body</p>', $decodedPage);
        self::assertStringContainsString('mcms-page-content', $decodedPage);
    }

    public function testDefaultThemeManifestIsLocalizedInRussian(): void
    {
        service('language')->setLocale('ru');

        $manager = new ThemeManager(service('settings'), ROOTPATH . 'themes');
        $theme = $manager->find('default');

        self::assertNotNull($theme);
        self::assertStringContainsString('адаптивный шаблон', $manager->displayDescription($theme));
        self::assertSame(
            'Основная навигация',
            $manager->displayLocationLabel($theme->menuLocations['header.primary']),
        );
    }
}
