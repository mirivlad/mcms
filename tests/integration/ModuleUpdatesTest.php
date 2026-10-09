<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModulePackages;
use MCMS\Core\Modules\ModuleRuntime;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Core\Modules\ModuleUpdates;

final class ModuleUpdatesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\\Settings'];
    private string $dir;
    private ModuleManager $modules;
    private ModulePackages $packages;
    private ModuleRuntime $runtime;
    private ModuleUpdates $updates;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/mcms-updates-' . bin2hex(random_bytes(8));
        $this->runtime = new ModuleRuntime($this->dir);
        $this->modules = new ModuleManager($this->db, new AdminRegistry(), new ModuleServiceRegistry(), ROOTPATH . 'modules', packagesPath: $this->dir . '/installed', runtime: $this->runtime);
        $this->packages = new ModulePackages($this->modules, $this->dir, $this->runtime);
        $this->updates = new ModuleUpdates($this->modules, $this->packages, $this->runtime, $this->dir);
        $preview = $this->packages->prepare($this->archive('1.0.0'));
        $this->packages->install($preview['token'], $preview['sha256']);
        $this->modules = new ModuleManager($this->db, new AdminRegistry(), new ModuleServiceRegistry(), ROOTPATH . 'modules', packagesPath: $this->dir . '/installed', runtime: $this->runtime);
        $this->packages = new ModulePackages($this->modules, $this->dir, $this->runtime);
        $this->updates = new ModuleUpdates($this->modules, $this->packages, $this->runtime, $this->dir);
        $this->db->table('mcms_modules')->insert(['module_id' => 'update_probe', 'version' => '1.0.0', 'enabled' => 0, 'installed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
    }

    protected function tearDown(): void
    {
        $this->runtime->release();
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->dir);
        parent::tearDown();
    }

    private function archive(string $version, array $changes = []): string
    {
        $path = tempnam(sys_get_temp_dir(), 'update-');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('module.json', json_encode(array_replace([
            'id' => 'update_probe', 'name' => 'Update probe', 'version' => $version,
            'namespace' => 'MCMS\\Modules\\UpdateProbe', 'provider' => 'MCMS\\Modules\\UpdateProbe\\Module', 'mcms' => '^0.1',
        ], $changes), JSON_THROW_ON_ERROR));
        $zip->addFromString('Module.php', '<?php throw new RuntimeException("Update provider executed");');
        $zip->close();
        // Register the fixture archive inside our own tree for cleanup.
        if (! is_dir($this->dir)) {
            mkdir($this->dir, 0755, true);
        }
        $result = $this->dir . '/' . bin2hex(random_bytes(8)) . '.zip';
        rename($path, $result);
        return $result;
    }

    private function queue(): array
    {
        $preview = $this->updates->prepare('update_probe', $this->archive('1.1.0'));
        return $this->updates->queue('update_probe', $preview['token'], $preview['sha256']);
    }

    public function testQueueDoesNotExecuteOrReplaceCodeAndCanBeCancelled(): void
    {
        $before = ModulePackages::fingerprint($this->dir . '/installed/UpdateProbe');
        $state = $this->queue();
        self::assertSame('queued', $state['status']);
        self::assertSame($before, ModulePackages::fingerprint($this->dir . '/installed/UpdateProbe'));
        self::assertSame('1.0.0', $this->modules->states()['update_probe']['version']);
        self::assertSame('1.0.0', ModulePackages::inspectArchive($this->updates->snapshot('update_probe'))['manifest']->version);
        try {
            $this->modules->enable('update_probe');
            self::fail('Queued update enabled');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('cancel', $e->getMessage());
        }
        $this->updates->cancel('update_probe', $state['operation']);
        self::assertNull($this->updates->state('update_probe'));
        self::assertNull($this->runtime->maintenance());
    }

    public function testFailureKeepsMaintenanceUntilDatabaseAndCodeRecovery(): void
    {
        $state = $this->queue();
        $this->updates->begin('update_probe', $state['operation'], 'apply');
        try {
            $this->updates->runWorker('update_probe', $state['operation'], 'apply');
            self::fail('Broken provider accepted');
        } catch (Throwable $e) {
            self::assertNotSame('', $e->getMessage());
        }
        self::assertSame('failed', $this->updates->state('update_probe')['status']);
        self::assertNotNull($this->runtime->maintenance());
        self::assertSame('1.1.0', json_decode(file_get_contents($this->dir . '/installed/UpdateProbe/module.json'), true)['version']);
        // The invalid provider failed before migrations; no DB changes to restore.
        $this->updates->begin('update_probe', $state['operation'], 'restore');
        $this->updates->runWorker('update_probe', $state['operation'], 'restore');
        self::assertNotNull($this->runtime->maintenance());
        $this->updates->finalize();
        self::assertNull($this->runtime->maintenance());
        self::assertSame('restored', $this->updates->state('update_probe')['status']);
        self::assertSame('1.0.0', json_decode(file_get_contents($this->dir . '/installed/UpdateProbe/module.json'), true)['version']);
    }

    public function testStaleSourceRejectedBeforeMaintenance(): void
    {
        $state = $this->queue();
        file_put_contents($this->dir . '/installed/UpdateProbe/Module.php', '<?php // changed');
        try {
            $this->updates->begin('update_probe', $state['operation'], 'apply');
            self::fail('Stale code accepted');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('changed', $e->getMessage());
        }
        self::assertNull($this->runtime->maintenance());
    }

    public function testTamperedCandidateRejectedBeforeMaintenance(): void
    {
        $state = $this->queue();
        file_put_contents($this->dir . '/updates/update_probe/candidate/Module.php', '<?php // changed');
        $this->expectException(RuntimeException::class);
        $this->updates->begin('update_probe', $state['operation'], 'apply');
    }

    public function testRestoreRejectsWrongDatabaseRegistry(): void
    {
        $state = $this->queue();
        $this->updates->begin('update_probe', $state['operation'], 'apply');
        $this->db->table('mcms_modules')->where('module_id', 'update_probe')->update(['version' => '1.1.0']);
        $this->expectExceptionMessage('Restore the database backup');
        $this->updates->begin('update_probe', $state['operation'], 'restore');
    }

    public function testEnabledTargetRefused(): void
    {
        $this->db->table('mcms_modules')->where('module_id', 'update_probe')->update(['enabled' => 1]);
        $this->expectExceptionMessage('Disable');
        $this->updates->prepare('update_probe', $this->archive('1.1.0'));
    }

    public function testBuiltInModuleRefused(): void
    {
        $this->db->table('mcms_modules')->insert(['module_id' => 'feedback', 'version' => '0.1.0', 'enabled' => 0, 'installed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        $this->expectException(RuntimeException::class);
        $this->updates->prepare('feedback', $this->archive('1.1.0'));
    }

    /** @dataProvider invalidReplacements */
    public function testIncompatibleReplacementRefused(string $version, array $changes): void
    {
        $this->expectException(RuntimeException::class);
        $this->updates->prepare('update_probe', $this->archive($version, $changes));
    }

    public static function invalidReplacements(): array
    {
        return [['1.0.0', []], ['0.9.0', []], ['1.1.0', ['id' => 'different']],
            ['1.1.0', ['mcms' => '^9.0']], ['1.1.0', ['namespace' => 'MCMS\\Modules\\Other', 'provider' => 'MCMS\\Modules\\Other\\Module']]];
    }

    public function testMaintenanceBlocksLifecycleAndModuleBoot(): void
    {
        $state = $this->queue();
        $this->updates->begin('update_probe', $state['operation'], 'apply');
        $this->modules->bootEnabled();
        $this->expectExceptionMessage('recovery');
        $this->modules->enable('pages');
    }

    public function testDisabledDependentConstraintIsChecked(): void
    {
        mkdir($this->dir . '/installed/Dependent');
        file_put_contents($this->dir . '/installed/Dependent/module.json', json_encode([
            'id' => 'dependent', 'name' => 'Dependent', 'version' => '1.0.0',
            'namespace' => 'MCMS\\Modules\\Dependent', 'provider' => 'MCMS\\Modules\\Dependent\\Module',
            'mcms' => '^0.1', 'requires' => [['id' => 'update_probe', 'version' => '1.0.*']],
        ], JSON_THROW_ON_ERROR));
        $this->expectExceptionMessage('incompatible with dependent');
        $this->updates->prepare('update_probe', $this->archive('1.1.0'));
    }

    public function testEnabledDependentIsRefused(): void
    {
        mkdir($this->dir . '/installed/Dependent');
        file_put_contents($this->dir . '/installed/Dependent/module.json', json_encode([
            'id' => 'dependent', 'name' => 'Dependent', 'version' => '1.0.0',
            'namespace' => 'MCMS\\Modules\\Dependent', 'provider' => 'MCMS\\Modules\\Dependent\\Module',
            'mcms' => '^0.1', 'requires' => ['update_probe'],
        ], JSON_THROW_ON_ERROR));
        $this->db->table('mcms_modules')->insert(['module_id' => 'dependent', 'version' => '1.0.0', 'enabled' => 1, 'installed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        $this->expectExceptionMessage('Disable the dependent module');
        $this->updates->prepare('update_probe', $this->archive('1.1.0'));
    }

    public function testMissingExistingMigrationIsRefusedWithoutChangingInstalledCode(): void
    {
        mkdir($this->dir . '/installed/UpdateProbe/Database/Migrations', 0755, true);
        file_put_contents($this->dir . '/installed/UpdateProbe/Database/Migrations/2026-10-09-000001_Original.php', '<?php // retained migration');
        $preview = $this->updates->prepare('update_probe', $this->archive('1.1.0'));
        try {
            $this->updates->queue('update_probe', $preview['token'], $preview['sha256']);
            self::fail('Missing historical migration accepted');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('retained unchanged', $e->getMessage());
        }
        self::assertNull($this->updates->state('update_probe'));
        self::assertFileExists($this->dir . '/installed/UpdateProbe/Module.php');
    }

    public function testInterruptedMaintenanceBeforeReceiptChangeCanRecover(): void
    {
        $state = $this->queue();
        $this->runtime->exclusive(fn () => $this->runtime->beginMaintenance('update_probe', $state['operation'], 'apply'));
        // A process can die between the separate atomic marker and receipt writes.
        $this->updates->begin('update_probe', $state['operation'], 'restore');
        $this->updates->runWorker('update_probe', $state['operation'], 'restore');
        $this->updates->finalize();
        self::assertNull($this->runtime->maintenance());
        self::assertSame('restored', $this->updates->state('update_probe')['status']);
    }

    public function testExclusiveOperationWaitsForExistingRuntimeReader(): void
    {
        $readerCode = 'require ' . var_export(ROOTPATH . 'src/Core/Modules/ModuleRuntime.php', true) . ';'
            . '$runtime = new \\MCMS\\Core\\Modules\\ModuleRuntime(' . var_export($this->dir, true) . ');'
            . '$runtime->readLock(); echo "ready\\n"; flush();'
            . 'register_shutdown_function(static function (): void { usleep(150000); file_put_contents('
            . var_export($this->dir . '/reader-finished', true) . ', "yes"); });';
        $process = proc_open([PHP_BINARY, '-r', $readerCode], [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        try {
            self::assertSame("ready\n", fgets($pipes[1]));
            $this->runtime->exclusive(function (): void {
                self::assertFileExists($this->dir . '/reader-finished');
            });
        } finally {
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process));
        }
    }

    public function testOperationOwnerMustMatch(): void
    {
        $this->queue();
        $this->expectExceptionMessage('state changed');
        $this->updates->cancel('update_probe', str_repeat('0', 32));
    }
}
