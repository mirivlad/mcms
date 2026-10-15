<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MCMS\Core\Modules\ModulePackages;
use MCMS\Core\Modules\ModuleRuntime;
use MCMS\Core\Packages\ZipPackage;
use MCMS\Core\Themes\ThemeManager;
use MCMS\Core\Themes\ThemePackages;
use MCMS\Core\Themes\ThemeUpdates;

final class ThemeUpdateRecoveryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\Settings'];
    private string $root;
    private ThemeManager $themes;
    private ThemePackages $packages;
    private ThemeUpdates $updates;
    private ModuleRuntime $runtime;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/mcms-theme-recovery-' . bin2hex(random_bytes(8));
        mkdir($this->root);
        $this->themes = new ThemeManager(service('settings'), ROOTPATH . 'tests/_support/FixtureThemes', packagesPath: $this->root);
        $this->runtime = new ModuleRuntime($this->root . '/runtime');
        $this->packages = new ThemePackages($this->themes, $this->root, $this->runtime);
        $this->updates = new ThemeUpdates($this->themes, $this->packages, $this->runtime, $this->root);
        $preview = $this->packages->prepare($this->archive('1.0.0'));
        $this->packages->install($preview['token'], $preview['sha256']);
    }

    protected function tearDown(): void
    {
        $this->runtime->release();
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() && ! $file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
        parent::tearDown();
    }

    private function archive(string $version): string
    {
        $path = $this->root . '/archive-' . $version . '.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('theme.json', json_encode([
            'id' => 'test-swap', 'name' => 'Swap fixture', 'namespace' => 'MCMS\Themes\SwapFixture',
            'version' => $version, 'mcms' => '^0.1', 'templates' => ['home' => 'home'],
        ]));
        $zip->addFromString('Views/home.php', 'Home ' . $version);
        $zip->close();
        return $path;
    }

    private function journal(string $stage, ?array $receipt): void
    {
        if (! is_dir($this->root . '/transactions')) {
            mkdir($this->root . '/transactions');
        }
        file_put_contents($this->root . '/transactions/test-swap.json', json_encode([
            'id' => 'test-swap', 'installed' => $this->root . '/installed/test-swap',
            'previous' => $this->root . '/history/test-swap/interrupted',
            'stage' => $stage, 'receipt' => $receipt,
        ]));
    }

    public function testInterruptedRollbackRetainsTheSnapshotAndCanBeRetried(): void
    {
        $preview = $this->updates->prepare('test-swap', $this->archive('1.1.0'));
        $this->updates->apply('test-swap', $preview['token'], $preview['sha256'], $preview['source']);
        $receipt = $this->updates->previous('test-swap');
        $installed = $this->root . '/installed/test-swap';
        rename($installed, $this->root . '/history/test-swap/interrupted');
        rename($receipt['path'], $installed);
        $this->journal($receipt['path'], $receipt);
        $this->updates->recover();
        self::assertSame('1.1.0', $this->themes->find('test-swap')->version);
        self::assertDirectoryExists($this->updates->previous('test-swap')['path']);
        $this->updates->rollback('test-swap', ModulePackages::fingerprint($installed));
        self::assertSame('1.0.0', $this->themes->find('test-swap')->version);
    }

    public function testInterruptedApplyCanReuseItsUnexpiredReview(): void
    {
        $archive = $this->archive('1.1.0');
        $preview = $this->updates->prepare('test-swap', $archive);
        $stage = $this->root . '/pending/' . $preview['token'];
        ZipPackage::extractArchive($archive, $stage, ThemePackages::inspectArchive(...));
        mkdir($this->root . '/history/test-swap', 0755, true);
        rename($this->root . '/installed/test-swap', $this->root . '/history/test-swap/interrupted');
        rename($stage, $this->root . '/installed/test-swap');
        $this->journal($stage, null);
        $this->updates->recover();
        $this->updates->apply('test-swap', $preview['token'], $preview['sha256'], $preview['source']);
        self::assertSame('1.1.0', $this->themes->find('test-swap')->version);
        self::assertDirectoryDoesNotExist($stage);
    }
}
