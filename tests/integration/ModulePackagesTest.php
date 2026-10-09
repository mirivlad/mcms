<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModulePackages;
use MCMS\Core\Modules\ModuleServiceRegistry;

final class ModulePackagesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\\Settings'];
    private string $dir;
    private ModulePackages $packages;
    private ModuleManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/mcms-packages-' . bin2hex(random_bytes(8));
        mkdir($this->dir . '/source', 0755, true);
        $this->manager = new ModuleManager($this->db, new AdminRegistry(), new ModuleServiceRegistry(), ROOTPATH . 'modules', packagesPath: $this->dir . '/packages/installed');
        $this->packages = new ModulePackages($this->manager, $this->dir . '/packages');
    }

    protected function tearDown(): void
    {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() && ! $file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->dir);
        parent::tearDown();
    }

    private function archive(array $changes = [], array $extra = []): string
    {
        $data = array_replace([
            'id' => 'package_probe', 'name' => 'Package probe', 'version' => '1.0.0',
            'namespace' => 'MCMS\\Modules\\PackageProbe', 'provider' => 'MCMS\\Modules\\PackageProbe\\Module',
            'mcms' => '^0.1', 'public_paths' => ['package-probe'],
        ], $changes);
        $zip = new ZipArchive();
        $path = $this->dir . '/' . bin2hex(random_bytes(4)) . '.zip';
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('module.json', json_encode($data, JSON_THROW_ON_ERROR));
        // Loading this provider during inspection, discovery or installation
        // would fail. A disabled package must not execute it.
        $zip->addFromString('Module.php', "<?php throw new RuntimeException('Provider executed before enable');");
        foreach ($extra as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        return $path;
    }

    public function testPreviewAndInstallationDoNotRunProviderOrMigrations(): void
    {
        $preview = $this->packages->prepare($this->archive([], ['Database/Migrations/2026-10-09-000001_Probe.php' => '<?php throw new RuntimeException("Migration executed");']));
        self::assertSame('package_probe', $preview['manifest']->id);
        self::assertCount(3, $preview['files']);
        self::assertSame([], $this->manager->states());
        $installed = $this->packages->install($preview['token'], $preview['sha256']);
        self::assertFileExists($installed->path . '/Module.php');
        self::assertFileDoesNotExist($this->dir . '/packages/pending/' . $preview['token'] . '.zip');
        $fresh = new ModuleManager($this->db, new AdminRegistry(), new ModuleServiceRegistry(), ROOTPATH . 'modules', packagesPath: $this->dir . '/packages/installed');
        self::assertArrayHasKey('package_probe', $fresh->catalog());
        $fresh->bootEnabled();
        self::assertSame([], $fresh->states());
    }

    public function testPackingRoundTripAndExistingOutputProtection(): void
    {
        $archive = $this->archive();
        $zip = new ZipArchive();
        $zip->open($archive);
        $zip->extractTo($this->dir . '/source');
        $zip->close();
        $output = $this->dir . '/built.zip';
        ModulePackages::pack($this->dir . '/source', $output);
        self::assertSame('package_probe', ModulePackages::inspectArchive($output)['manifest']->id);
        $hash = hash_file('sha256', $output);
        try {
            ModulePackages::pack($this->dir . '/source', $output);
            self::fail('Existing output overwritten');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('already exists', $e->getMessage());
        }
        self::assertSame($hash, hash_file('sha256', $output));
    }

    public static function unsafePaths(): array
    {
        return array_map(static fn ($path) => [$path], [
            '../escape.php', '/absolute.php', 'C:/windows.php', 'Views/../escape.php',
            'Views\\escape.php', '.env', 'Config/Routes.php', 'config/Events.php',
            'Commands/Probe.php', 'vendor/library.php', 'Views/file.php.',
            'Views/file.php ', 'module.JSON', 'Module.php/child',
        ]);
    }

    /** @dataProvider unsafePaths */
    public function testRejectsUnsafeAndAmbiguousPaths(string $path): void
    {
        $this->expectException(RuntimeException::class);
        $this->packages->prepare($this->archive([], [$path => 'unsafe']));
    }

    public function testRejectsSymlinkAndOversizedEntry(): void
    {
        $path = $this->archive([], ['link.php' => '../../outside']);
        $zip = new ZipArchive();
        $zip->open($path);
        $zip->setExternalAttributesName('link.php', ZipArchive::OPSYS_UNIX, 0120777 << 16);
        $zip->close();
        try {
            $this->packages->prepare($path);
            self::fail('Symlink accepted');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('links', $e->getMessage());
        }
        $this->expectException(RuntimeException::class);
        $this->packages->prepare($this->archive([], ['large.txt' => str_repeat('a', 8 * 1024 * 1024 + 1)]));
    }

    public static function incompatibleManifests(): array
    {
        return [
            [['mcms' => '^99.0']], [['id' => 'media']],
            [['namespace' => 'MCMS\\Modules\\Media', 'provider' => 'MCMS\\Modules\\Media\\Module']],
            [['namespace' => 'App', 'provider' => 'App\\Module']],
            [['requires' => [['id' => 'missing', 'version' => '^1.0']]]],
            [['requires' => [['id' => 'media', 'version' => '^99.0']]]],
            [['requires' => ['package_probe']]], [['requires' => 'media']],
            [['public_paths' => ['admin']]], [['public_paths' => ['news']]],
            [['version' => 'bad']], [['provider' => 'MCMS\\Core\\Module']], [['id' => 'core']],
            [['id' => "package_probe\n"]],
        ];
    }

    /** @dataProvider incompatibleManifests */
    public function testRejectsIncompatibleOrConflictingManifest(array $changes): void
    {
        $this->expectException(Exception::class);
        $this->packages->prepare($this->archive($changes));
    }

    public function testCannotReplaceExistingModuleEvenWithStaleCatalog(): void
    {
        $archive = $this->archive();
        $first = $this->packages->prepare($archive);
        $second = $this->packages->prepare($archive);
        $this->packages->install($first['token'], $first['sha256']);
        $this->expectException(RuntimeException::class);
        $this->packages->install($second['token'], $second['sha256']);
    }

    public function testChangedExpiredAndCancelledPreviewsAreRejected(): void
    {
        foreach (['changed', 'expired', 'cancelled'] as $mode) {
            $preview = $this->packages->prepare($this->archive());
            $path = $this->dir . '/packages/pending/' . $preview['token'] . '.zip';
            match ($mode) {
                'changed' => file_put_contents($path, 'changed'),
                'expired' => touch($path, time() - 3601),
                'cancelled' => $this->packages->cancel($preview['token']),
            };
            clearstatcache();
            try {
                $this->packages->install($preview['token'], $preview['sha256']);
                self::fail('Invalid preview accepted: ' . $mode);
            } catch (RuntimeException $e) {
                self::assertStringContainsString('expired or', $e->getMessage());
            }
        }
        self::assertDirectoryDoesNotExist($this->dir . '/packages/installed/PackageProbe');
    }

    public function testCompatibleDependencyMayRemainDisabledUntilExplicitEnable(): void
    {
        $preview = $this->packages->prepare($this->archive(['requires' => [['id' => 'media', 'version' => '*']]]));
        $this->packages->install($preview['token'], $preview['sha256']);
        self::assertSame([], $this->manager->states());
    }

    public function testInvalidProviderCannotActivateOrRunMigrations(): void
    {
        $archive = $this->archive([
            'id' => 'invalid_probe', 'namespace' => 'MCMS\\Modules\\InvalidPackageProbe',
            'provider' => 'MCMS\\Modules\\InvalidPackageProbe\\Module',
        ], ['Database/Migrations/2026-10-09-000001_Probe.php' => '<?php throw new RuntimeException("Migration must not run");']);
        $zip = new ZipArchive();
        $zip->open($archive);
        $zip->addFromString('Module.php', '<?php namespace MCMS\\Modules\\InvalidPackageProbe; final class Module {}');
        $zip->close();
        $preview = $this->packages->prepare($archive);
        $installed = $this->packages->install($preview['token'], $preview['sha256']);
        // Other integration fixtures reset the SPL loader. Load this fixture
        // type explicitly; Docker smoke verifies actual runtime autoloading.
        require $installed->path . '/Module.php';
        $fresh = new ModuleManager($this->db, new AdminRegistry(), new ModuleServiceRegistry(), ROOTPATH . 'modules', packagesPath: $this->dir . '/packages/installed');
        try {
            $fresh->enable('invalid_probe');
            self::fail('Invalid provider activated');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('must implement', $e->getMessage());
        }
        self::assertArrayNotHasKey('invalid_probe', $fresh->states());
        $fresh->bootEnabled();
    }
}
