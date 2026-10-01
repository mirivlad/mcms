<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MCMS\Core\Themes\ThemeManager;

final class ThemeManagerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = [
        'App',
        'CodeIgniter\\Settings',
    ];

    public function testCatalogActivationAndLogicalRendering(): void
    {
        $manager = new ThemeManager(
            service('settings'),
            ROOTPATH . 'tests/_support/FixtureThemes',
        );

        self::assertSame(['alpha', 'beta'], array_keys($manager->catalog()));
        self::assertNull($manager->active());

        $theme = $manager->activate('alpha');

        self::assertSame('alpha', $theme->id);
        self::assertSame('alpha', $manager->activeId());
        self::assertSame('alpha', $manager->active()?->id);

        $rendered = $manager->render('home', ['message' => '<hello>']);

        self::assertStringContainsString('Alpha: &lt;hello&gt;', $rendered);
        self::assertStringContainsString(
            '/assets/themes/alpha/css/theme.css',
            $manager->assetUrl('css/theme.css'),
        );
    }

    public function testEnsureActiveSelectsPreferredTheme(): void
    {
        service('settings')->forget('Core.theme');

        $manager = new ThemeManager(
            service('settings'),
            ROOTPATH . 'tests/_support/FixtureThemes',
        );

        $theme = $manager->ensureActive('beta');

        self::assertNotNull($theme);
        self::assertSame('beta', $theme->id);
        self::assertSame('beta', $manager->activeId());
    }

    public function testUnknownThemeCannotBeActivated(): void
    {
        $manager = new ThemeManager(
            service('settings'),
            ROOTPATH . 'tests/_support/FixtureThemes',
        );

        $this->expectException(RuntimeException::class);
        $manager->activate('missing');
    }
}
