<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Public templates must agree on optional modules and editor shortcuts. */
final class PublicThemeCompositionTest extends TestCase
{
    public function testEveryBundledAndSampleThemeUsesSharedPublicComponents(): void
    {
        $themes = array_merge(
            glob(ROOTPATH . 'themes/mcms-*'),
            [ROOTPATH . 'examples/themes/mcms-atelier'],
        );
        self::assertCount(5, $themes);

        foreach ($themes as $theme) {
            $layout = file_get_contents($theme . '/Views/layout.php');
            self::assertStringContainsString("view('themes/content_assets')", $layout, $theme);
            self::assertStringContainsString("view('themes/content_actions'",
                file_get_contents($theme . '/Views/galleries/album.php'), $theme . '/Views/galleries/album.php');

            foreach (['page.php' => 'pages.page', 'news/article.php' => 'news.article'] as $file => $target) {
                $contents = file_get_contents($theme . '/Views/' . $file);
                self::assertStringContainsString("view('themes/content_actions'", $contents, "{$theme}/{$file}");
                self::assertStringContainsString("view('themes/content_interactions'", $contents, "{$theme}/{$file}");
                self::assertStringContainsString("'targetType' => '{$target}'", $contents);
                self::assertStringContainsString('PublicContent::body(', $contents);
                self::assertSame(1, substr_count($contents, '<h1'), "{$theme}/{$file} should emit only its single theme heading");
            }
        }
    }

    public function testCatalogueTemplatesExposeEditorActionsAndAlignedInteractions(): void
    {
        $detail = file_get_contents(ROOTPATH . 'modules/Catalogue/Views/_product.php');
        $listing = file_get_contents(ROOTPATH . 'modules/Catalogue/Views/_index.php');
        self::assertStringContainsString("view('themes/content_actions'", $detail);
        self::assertStringContainsString("view('themes/content_interactions'", $detail);
        self::assertStringContainsString("view('themes/content_actions'", $listing);
        self::assertStringContainsString('PublicContent::body(', $detail);
    }

    public function testEditorActionsArePermissionGated(): void
    {
        $actions = file_get_contents(ROOTPATH . 'app/Views/themes/content_actions.php');
        self::assertStringContainsString("auth()->loggedIn()", $actions);
        self::assertStringContainsString("->can(", $actions);
        self::assertStringContainsString("'/admin/catalogue/%d/edit'", $actions);
        self::assertStringContainsString("'/admin/news/%d/edit'", $actions);
        self::assertStringContainsString("'/admin/pages/%d/edit'", $actions);
        self::assertStringContainsString("'/admin/galleries/%d/edit'", $actions);
    }
}
