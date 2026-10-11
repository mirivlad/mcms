<?php

declare(strict_types=1);

use MCMS\Core\Themes\ThemePackages;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class AtelierExamplePackageTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testExternalViewTranslationClassLoadsFromThemeNamespace(): void
    {
        service('autoloader')->addNamespace(
            'MCMS\\Themes\\Atelier',
            ROOTPATH . 'examples/themes/mcms-atelier'
        );
        self::assertTrue(class_exists('MCMS\\Themes\\Atelier\\Views\\Text'));
        $request = service('request');
        $oldLocale = $request->getLocale();
        try {
            $request->setLocale('ru');
            self::assertSame('Просмотр изображений', \MCMS\Themes\Atelier\Views\Text::get('viewer'));
            $request->setLocale('en');
            self::assertSame('Image viewer', \MCMS\Themes\Atelier\Views\Text::get('viewer'));
        } finally {
            $request->setLocale($oldLocale);
        }
    }

    public function testShippedAtelierArchiveMatchesSourceContract(): void
    {
        $zip = ROOTPATH . 'examples/packages/mcms-atelier-1.0.0.zip';
        $example = ROOTPATH . 'examples/themes/mcms-atelier';
        self::assertFileExists($zip);

        $report = ThemePackages::inspectArchive($zip);
        $manifest = $report['manifest'];
        self::assertSame('mcms-atelier', $manifest->id);
        self::assertSame('MCMS\\Themes\\Atelier', $manifest->namespace);
        self::assertSame('1.0.0', $manifest->version);
        self::assertTrue($manifest->external);
        self::assertCount(12, $manifest->templates);

        $archive = new ZipArchive();
        self::assertTrue($archive->open($zip, ZipArchive::RDONLY));
        try {
            $files = $report['files'];
            foreach ($files as $path) {
                self::assertFileExists($example . '/' . $path);
                self::assertSame(
                    file_get_contents($example . '/' . $path),
                    $archive->getFromName($path),
                    "Source file differs from packaged file: {$path}"
                );
            }
            $allPaths = [];
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($example, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                self::assertTrue($file->isFile());
                $allPaths[] = substr($file->getPathname(), strlen($example) + 1);
            }
            sort($allPaths);
            sort($files);
            self::assertSame($allPaths, $files);
        } finally {
            $archive->close();
        }
    }
}
