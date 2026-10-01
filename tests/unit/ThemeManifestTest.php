<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use MCMS\Core\Themes\ThemeManifest;

final class ThemeManifestTest extends CIUnitTestCase
{
    public function testManifestLoadsTemplatesAndMenuLocations(): void
    {
        $manifest = ThemeManifest::fromFile(ROOTPATH . 'tests/_support/FixtureThemes/alpha/theme.json');

        self::assertSame('alpha', $manifest->id);
        self::assertSame('Alpha theme', $manifest->name);
        self::assertSame('1.0.0', $manifest->version);
        self::assertSame(
            'Tests\\Support\\FixtureThemes\\Alpha\\Views\\home',
            $manifest->view('home'),
        );
        self::assertSame(
            'Tests\\Support\\FixtureThemes\\Alpha\\Views\\content\\page',
            $manifest->view('page'),
        );
        self::assertNull($manifest->view('missing'));
        self::assertSame(2, $manifest->menuLocations['header.primary']['max_depth']);
        self::assertSame(1, $manifest->menuLocations['footer']['max_depth']);
        self::assertSame('assets/themes/alpha/', $manifest->assetBaseUrl());
    }

    public function testDefaultThemeDeclaresPublicTemplatesAndMenuLocations(): void
    {
        $manifest = ThemeManifest::fromFile(ROOTPATH . 'themes/mcms-default/theme.json');

        self::assertSame('mcms-default', $manifest->id);
        self::assertSame('MCMS\\Themes\\DefaultTheme\\Views\\home', $manifest->view('home'));
        self::assertSame('MCMS\\Themes\\DefaultTheme\\Views\\page', $manifest->view('page'));
        self::assertSame('MCMS\\Themes\\DefaultTheme\\Views\\news\\index', $manifest->view('news.index'));
        self::assertSame('MCMS\\Themes\\DefaultTheme\\Views\\news\\article', $manifest->view('news.article'));
        self::assertSame(
            ['footer.legal', 'footer.primary', 'header.primary'],
            array_keys($manifest->menuLocations),
        );
        self::assertSame(2, $manifest->menuLocations['header.primary']['max_depth']);
        self::assertSame(1, $manifest->menuLocations['footer.legal']['max_depth']);
    }

    public function testManifestRejectsTraversalInTemplatePath(): void
    {
        $path = WRITEPATH . 'theme-invalid.json';

        file_put_contents($path, json_encode([
            'id' => 'invalid',
            'name' => 'Invalid',
            'version' => '1.0.0',
            'namespace' => 'Tests\\Invalid',
            'templates' => ['home' => '../secret'],
        ], JSON_THROW_ON_ERROR));

        try {
            $this->expectException(InvalidArgumentException::class);
            ThemeManifest::fromFile($path);
        } finally {
            @unlink($path);
        }
    }
}
