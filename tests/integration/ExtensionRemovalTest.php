<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModulePackages;
use MCMS\Core\Modules\ModuleRuntime;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Core\Packages\ExtensionRemoval;
use MCMS\Core\Packages\PackageState;
use MCMS\Core\Themes\ThemeManager;
use MCMS\Core\Themes\ThemePackages;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class ExtensionRemovalTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\\Settings'];
    private string $dir;
    private ModuleManager $modules;
    private ModulePackages $packages;
    private ThemeManager $themes;
    private ModuleRuntime $runtime;
    private ExtensionRemoval $removal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/mcms-removal-' . bin2hex(random_bytes(8));
        mkdir($this->dir);
        $this->runtime = new ModuleRuntime($this->dir . '/modules');
        $this->modules = new ModuleManager($this->db, new AdminRegistry(), new ModuleServiceRegistry(), ROOTPATH . 'modules',
            packagesPath: $this->dir . '/modules/installed', runtime: $this->runtime);
        $this->packages = new ModulePackages($this->modules, $this->dir . '/modules', $this->runtime);
        $this->themes = new ThemeManager(service('settings'), ROOTPATH . 'tests/_support/FixtureThemes', packagesPath: $this->dir . '/themes');
        $this->removal = new ExtensionRemoval($this->db, $this->modules, $this->themes, $this->runtime, $this->dir . '/modules', $this->dir . '/themes');
    }

    protected function tearDown(): void
    {
        $this->db->table(config('Migrations')->table)->where('namespace', 'MCMS\\Modules\\LifecycleDemo')->delete();
        \Config\Database::forge($this->db)->dropTable('lifecycle_demo_notes', true);
        $this->runtime->release();
        PackageState::remove($this->dir);
        parent::tearDown();
    }

    private function archive(string $version = '1.0.0', bool $changedMigration = false): string
    {
        $path = $this->dir . '/' . bin2hex(random_bytes(6)) . '.zip';
        ModulePackages::pack(ROOTPATH . 'examples/modules/lifecycle-demo', $path);
        $zip = new ZipArchive();
        $zip->open($path);
        $manifest = json_decode($zip->getFromName('module.json'), true);
        $manifest['version'] = $version;
        $zip->addFromString('module.json', json_encode($manifest));
        if ($changedMigration) {
            $zip->addFromString('Database/Migrations/2026-10-16-000001_CreateDemoNotes.php', '<?php // changed');
        }
        $zip->close();
        return $path;
    }

    private function install(string $version = '1.0.0'): void
    {
        $preview = $this->packages->prepare($this->archive($version));
        $this->packages->install($preview['token'], $preview['sha256']);
    }

    private function remove(bool $purge = false): void
    {
        $preview = $this->removal->preview('module', 'lifecycle-demo');
        $this->removal->remove('module', 'lifecycle-demo', $purge, $preview['fingerprint'], true);
    }

    public function testSameVersionReinstallRetainsRealDataSettingsFilesAndMigrationHistory(): void
    {
        $this->install();
        $this->modules->enable('lifecycle-demo');
        service('settings')->set('lifecycle-demo.greeting', 'Kept greeting');
        mkdir($this->dir . '/modules/data/lifecycle-demo', 0700, true);
        file_put_contents($this->dir . '/modules/data/lifecycle-demo/probe.txt', 'kept file');
        $history = $this->modules->migrationFingerprint('MCMS\\Modules\\LifecycleDemo');
        $this->remove();
        self::assertSame(0, (int) $this->modules->states()['lifecycle-demo']['enabled']);
        self::assertArrayNotHasKey('lifecycle-demo', $this->modules->catalog());
        self::assertSame('1.0.0', $this->removal->retained('module', 'lifecycle-demo')['schema_version']);
        $this->install();
        self::assertSame(0, (int) $this->modules->states()['lifecycle-demo']['enabled']);
        self::assertSame('Kept greeting', service('settings')->get('lifecycle-demo.greeting'));
        self::assertSame('kept file', file_get_contents($this->dir . '/modules/data/lifecycle-demo/probe.txt'));
        self::assertSame($history, $this->modules->migrationFingerprint('MCMS\\Modules\\LifecycleDemo'));
        $this->modules->enable('lifecycle-demo');
        self::assertSame(1, $this->db->table('lifecycle_demo_notes')->countAllResults());
        self::assertSame($history, $this->modules->migrationFingerprint('MCMS\\Modules\\LifecycleDemo'));
    }

    public function testNewerRetainedInstallAllowedButOldOrChangedMigrationsRejected(): void
    {
        $this->install();
        $this->modules->enable('lifecycle-demo');
        $this->remove();
        foreach ([['0.9.0', false], ['1.0.0', true]] as [$version, $changed]) {
            try {
                $this->packages->prepare($this->archive($version, $changed));
                self::fail('Unsafe retained schema accepted');
            } catch (RuntimeException) {
            }
        }
        $this->install('1.1.0');
        self::assertSame('1.1.0', $this->modules->catalog()['lifecycle-demo']->version);
        self::assertSame(0, (int) $this->modules->states()['lifecycle-demo']['enabled']);
        $this->modules->enable('lifecycle-demo');
        self::assertSame(1, $this->db->table('lifecycle_demo_notes')->countAllResults());
    }

    public function testPurgeAndCleanReinstallation(): void
    {
        $this->install();
        $this->modules->enable('lifecycle-demo');
        service('settings')->set('lifecycle-demo.greeting', 'Remove greeting');
        $preview = $this->removal->preview('module', 'lifecycle-demo');
        self::assertNull($preview['purgeError']);
        try {
            $this->removal->remove('module', 'lifecycle-demo', true, $preview['fingerprint']);
            self::fail('Backup confirmation bypassed');
        } catch (RuntimeException) {
        }
        $this->remove(true);
        self::assertFalse($this->db->tableExists('lifecycle_demo_notes'));
        self::assertArrayNotHasKey('lifecycle-demo', $this->modules->states());
        self::assertNull(service('settings')->get('lifecycle-demo.greeting'));
        self::assertNull($this->removal->retained('module', 'lifecycle-demo'));
        $this->install();
        $this->modules->enable('lifecycle-demo');
        self::assertSame(1, $this->db->table('lifecycle_demo_notes')->countAllResults());
        self::assertSame('Hello', service('settings')->get('lifecycle-demo.greeting'));
    }

    public function testDisabledAndCodeOnlyDependentsBlockBothModesAtApply(): void
    {
        $this->install();
        $preview = $this->removal->preview('module', 'lifecycle-demo');
        mkdir($this->dir . '/modules/installed/Dependent', 0700, true);
        $manifest = json_decode(file_get_contents(ROOTPATH . 'examples/modules/lifecycle-demo/module.json'), true);
        $manifest = array_replace($manifest, ['id' => 'dependent', 'namespace' => 'MCMS\\Modules\\Dependent',
            'provider' => 'MCMS\\Modules\\Dependent\\Module', 'requires' => ['lifecycle-demo']]);
        file_put_contents($this->dir . '/modules/installed/Dependent/module.json', json_encode($manifest));
        foreach ([false, true] as $purge) {
            try {
                $this->removal->remove('module', 'lifecycle-demo', $purge, $preview['fingerprint'], true);
                self::fail('Dependent removed');
            } catch (RuntimeException $e) {
                self::assertStringContainsString('dependent', $e->getMessage());
            }
        }
        self::assertDirectoryExists($this->dir . '/modules/installed/LifecycleDemo');
    }

    public function testBuiltinModulesAreProtected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->removal->preview('module', 'pages');
    }

    public function testInterruptedRemovalResumesAndCommitGapClearsMaintenance(): void
    {
        $this->install();
        $this->modules->enable('lifecycle-demo');
        $this->runtime->exclusive(fn () => $this->runtime->beginMaintenance('other', 'busy', 'apply'));
        // Simulate a receipt persisted just before a process exits.
        $state = ['id' => 'lifecycle-demo', 'type' => 'module', 'operation' => 'removal-test', 'purge' => false,
            'folder' => 'LifecycleDemo', 'retained' => ['version' => '1.0.0'], 'resources' => []];
        PackageState::write($this->dir . '/modules/removals/lifecycle-demo.json', $state);
        try {
            $this->removal->recover();
            self::fail('Other maintenance owner ignored');
        } catch (RuntimeException) {
        }
        $this->runtime->exclusive(fn () => $this->runtime->finishMaintenance('other', 'busy'));
        $this->removal->recover();
        self::assertDirectoryDoesNotExist($this->dir . '/modules/installed/LifecycleDemo');
        self::assertSame(0, (int) $this->modules->states()['lifecycle-demo']['enabled']);
        self::assertNull($this->runtime->maintenance());
        $this->runtime->exclusive(fn () => $this->runtime->beginMaintenance('lifecycle-demo', 'committed', 'remove'));
        $this->removal->recover();
        self::assertNull($this->runtime->maintenance());
    }

    public function testThemeKeepSameVersionReinstallAndPurgeProtectActiveAndBuiltin(): void
    {
        $packages = new ThemePackages($this->themes, $this->dir . '/themes', $this->runtime);
        $archive = ROOTPATH . 'examples/packages/mcms-atelier-1.1.2.zip';
        $preview = $packages->prepare($archive);
        $packages->install($preview['token'], $preview['sha256']);
        $this->themes->activate('mcms-atelier');
        $preview = $this->removal->preview('theme', 'mcms-atelier');
        self::assertNotEmpty($preview['blockers']);
        $this->themes->activate('alpha');
        service('settings')->set('Core.themeMenuAssignments', ['mcms-atelier' => ['header.primary' => 42], 'alpha' => []]);
        $preview = $this->removal->preview('theme', 'mcms-atelier');
        $this->removal->remove('theme', 'mcms-atelier', false, $preview['fingerprint']);
        self::assertNull($this->themes->find('mcms-atelier'));
        $preview = $packages->prepare($archive);
        $packages->install($preview['token'], $preview['sha256']);
        self::assertSame(42, service('settings')->get('Core.themeMenuAssignments')['mcms-atelier']['header.primary']);
        $preview = $this->removal->preview('theme', 'mcms-atelier');
        $this->removal->remove('theme', 'mcms-atelier', true, $preview['fingerprint'], true);
        self::assertArrayNotHasKey('mcms-atelier', service('settings')->get('Core.themeMenuAssignments'));
        self::assertArrayHasKey('alpha', service('settings')->get('Core.themeMenuAssignments'));
        $this->expectException(RuntimeException::class);
        $this->removal->preview('theme', 'alpha');
    }
}
