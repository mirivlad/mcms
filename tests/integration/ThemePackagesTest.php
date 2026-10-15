<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MCMS\Core\Themes\ThemeManager;
use MCMS\Core\Themes\ThemePackages;

final class ThemePackagesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\\Settings'];
    private string $dir;
    private ThemePackages $packages;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/mcms-theme-' . bin2hex(random_bytes(8));
        mkdir($this->dir);
        $this->packages = new ThemePackages($this->manager(), $this->dir . '/packages');
    }

    protected function tearDown(): void
    {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() && ! $file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->dir);
        parent::tearDown();
    }

    private function manager(): ThemeManager
    {
        return new ThemeManager(service('settings'), ROOTPATH . 'tests/_support/FixtureThemes', packagesPath: $this->dir . '/packages');
    }

    private function archive(array $changes = [], array $extra = []): string
    {
        $data = array_replace([
            'id' => 'theme-probe', 'name' => 'Theme probe', 'version' => '1.0.0',
            'namespace' => 'MCMS\\Themes\\PackageProbe', 'mcms' => '^0.1',
            'templates' => ['home' => 'home'], 'screenshot' => 'preview.svg',
        ], $changes);
        $path = $this->dir . '/' . bin2hex(random_bytes(4)) . '.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        foreach (array_replace([
            'theme.json' => json_encode($data, JSON_THROW_ON_ERROR),
            'Views/home.php' => '<?php throw new RuntimeException("Template executed");',
            'assets/style.css' => 'body { color: #123456; }',
            'assets/preview.svg' => '<svg xmlns="http://www.w3.org/2000/svg"/>',
        ], $extra) as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        return $path;
    }

    public function testInstallationDoesNotExecuteOrActivateTemplatesAndSurvivesDiscovery(): void
    {
        $this->manager()->activate('alpha');
        $preview = $this->packages->prepare($this->archive());
        self::assertSame('theme-probe', $preview['manifest']->id);
        $installed = $this->packages->install($preview['token'], $preview['sha256']);
        self::assertTrue($installed->external);
        self::assertFileExists($installed->path . '/Views/home.php');
        self::assertFileDoesNotExist($this->dir . '/packages/pending/' . $preview['token'] . '.zip');
        $fresh = $this->manager();
        self::assertSame('alpha', $fresh->activeId());
        self::assertSame('theme-probe', $fresh->find('theme-probe')?->id);
        self::assertStringContainsString('/theme-assets/theme-probe/1.0.0/style.css', $fresh->assetUrl('style.css', $installed));
        $fresh->activate('theme-probe');
        $level = ob_get_level();
        try {
            $fresh->render('home');
            self::fail('Template was not executed after activation');
        } catch (RuntimeException $e) {
            self::assertSame('Template executed', $e->getMessage());
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }

    public function testPackingRenderingAndSettingsArePreservedAcrossThemeSwitches(): void
    {
        $archive = $this->archive(['home_defaults' => ['title' => 'Original']], ['Views/home.php' => 'External <?= esc($message) ?>']);
        mkdir($this->dir . '/source');
        $zip = new ZipArchive();
        $zip->open($archive);
        $zip->extractTo($this->dir . '/source');
        $zip->close();
        ThemePackages::pack($this->dir . '/source', $this->dir . '/packed.zip');
        $preview = $this->packages->prepare($this->dir . '/packed.zip');
        $this->packages->install($preview['token'], $preview['sha256']);
        $fresh = $this->manager();
        $fresh->activate('theme-probe');
        $fresh->saveHomeContent('theme-probe', ['title' => 'Saved title']);
        self::assertStringContainsString('External &lt;hello&gt;', $fresh->render('home', ['message' => '<hello>']));
        $fresh->activate('alpha');
        $new = $this->manager();
        $new->activate('theme-probe');
        self::assertSame('Saved title', $new->homeContent()['title']);
        $this->expectExceptionMessage('already exists');
        ThemePackages::pack($this->dir . '/source', $this->dir . '/packed.zip');
    }

    private function updates(): \MCMS\Core\Themes\ThemeUpdates
    {
        return new \MCMS\Core\Themes\ThemeUpdates($this->manager(), $this->packages,
            new \MCMS\Core\Modules\ModuleRuntime($this->dir . '/runtime'), $this->dir . '/packages');
    }

    public function testActiveThemeUpdateAndRollbackPreserveSettingsDataAndOldAssets(): void
    {
        $first = $this->packages->prepare($this->archive(['home_defaults' => ['title' => 'Original']]));
        $this->packages->install($first['token'], $first['sha256']);
        $manager = $this->manager();
        $manager->activate('theme-probe');
        service('settings')->set('Core.themeMenuAssignments', ['theme-probe' => ['header.primary' => 77]]);
        $manager->saveHomeContent('theme-probe', ['title' => 'My home']);
        $manager->saveAppearance('theme-probe', ['accent' => '#123456', 'phone' => '123']);
        mkdir($this->dir . '/packages/data/theme-probe', 0755, true);
        file_put_contents($this->dir . '/packages/data/theme-probe/user.txt', 'Keep me');
        $updates = $this->updates();
        $preview = $updates->prepare('theme-probe', $this->archive(['version' => '1.1.0', 'home_defaults' => ['title' => 'New default']], ['assets/style.css' => 'new bytes']));
        $updates->apply('theme-probe', $preview['token'], $preview['sha256'], $preview['source']);
        $manager = $this->manager();
        self::assertSame('1.1.0', $manager->active()->version);
        self::assertSame(['theme-probe' => ['header.primary' => 77]], service('settings')->get('Core.themeMenuAssignments'));
        self::assertSame('My home', $manager->homeContent()['title']);
        self::assertSame('123', $manager->appearance()['phone']);
        self::assertSame('Keep me', file_get_contents($this->dir . '/packages/data/theme-probe/user.txt'));
        self::assertSame('body { color: #123456; }', file_get_contents($updates->assetTheme('theme-probe', '1.0.0')->path . '/assets/style.css'));
        $source = \MCMS\Core\Modules\ModulePackages::fingerprint($manager->active()->path);
        $updates->rollback('theme-probe', $source);
        self::assertSame('1.0.0', $this->manager()->active()->version);
        self::assertSame('new bytes', file_get_contents($updates->assetTheme('theme-probe', '1.1.0')->path . '/assets/style.css'));
        self::assertSame('My home', $this->manager()->homeContent()['title']);
    }

    public function testUpdateRejectsIdentityOlderVersionChangedArchiveAndStaleSource(): void
    {
        $first = $this->packages->prepare($this->archive());
        $this->packages->install($first['token'], $first['sha256']);
        $updates = $this->updates();
        foreach ([['version' => '1.0.0'], ['id' => 'different', 'version' => '2.0.0'],
            ['namespace' => 'MCMS\\Themes\\Different', 'version' => '2.0.0'], ['mcms' => '^999', 'version' => '2.0.0']] as $changes) {
            try {
                $updates->prepare('theme-probe', $this->archive($changes));
                self::fail('Invalid update accepted');
            } catch (RuntimeException) {
            }
        }
        $preview = $updates->prepare('theme-probe', $this->archive(['version' => '2.0.0']));
        file_put_contents($this->dir . '/packages/installed/theme-probe/assets/style.css', 'concurrent edit');
        try {
            $updates->apply('theme-probe', $preview['token'], $preview['sha256'], $preview['source']);
            self::fail('Stale source accepted');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('changed', $e->getMessage());
        }
        self::assertSame('1.0.0', $this->manager()->find('theme-probe')->version);
    }

    public function testInterruptedSwapIsRecoveredBeforeDiscoveryAndRecoveryIsIdempotent(): void
    {
        $first = $this->packages->prepare($this->archive());
        $theme = $this->packages->install($first['token'], $first['sha256']);
        $root = $this->dir . '/packages';
        mkdir($root . '/history/theme-probe', 0755, true);
        mkdir($root . '/transactions');
        $previous = $root . '/history/theme-probe/interrupted';
        rename($theme->path, $previous);
        file_put_contents($root . '/transactions/theme-probe.json', json_encode([
            'id' => 'theme-probe', 'installed' => $theme->path, 'previous' => $previous,
            'stage' => $root . '/pending/stage', 'receipt' => null,
        ]));
        $this->updates()->recover();
        $this->updates()->recover();
        self::assertSame('1.0.0', $this->manager()->find('theme-probe')->version);
        self::assertSame([], glob($root . '/transactions/*.json'));
    }

    public function testSyntaxFailureDoesNotReplaceThemeOrActiveSelection(): void
    {
        $first = $this->packages->prepare($this->archive());
        $this->packages->install($first['token'], $first['sha256']);
        $this->manager()->activate('theme-probe');
        $updates = $this->updates();
        $preview = $updates->prepare('theme-probe', $this->archive(['version' => '2.0.0'], ['Views/home.php' => '<?php syntax error !']));
        try {
            $updates->apply('theme-probe', $preview['token'], $preview['sha256'], $preview['source']);
            self::fail('Broken PHP accepted');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Invalid PHP template', $e->getMessage());
        }
        self::assertSame('1.0.0', $this->manager()->active()->version);
        self::assertNull($updates->previous('theme-probe'));
    }

    public static function invalidPackages(): array
    {
        return [
            [['id' => 'package'], []], [['id' => 'images'], []], [['id' => 'alpha'], []], [['mcms' => null], []], [['mcms' => '^999'], []],
            [['namespace' => 'App\\Controllers'], []], [['version' => '1.2'], []],
            [['templates' => []], []], [['templates' => ['home' => 'missing']], []],
            [['screenshot' => 'missing.svg'], []], [['screenshot' => 'style.css'], []],
            [['name' => '@MCMS.themes.title'], []], [['description' => []], []],
            [[], ['../escape.php' => '']], [[], ['Config/Routes.php' => '']],
            [[], ['Language/en/MCMS.php' => '']], [[], ['assets/evil.php' => '']],
            [[], ['assets/page.html' => '']], [[], ['Views/helper.js' => '']],
            [[], ['views/second.php' => '']], [[], ['Views/home.php/child.php' => '']],
            [[], ['assets/style.CSS' => '']], [[], ['theme.json/' => '']],
        ];
    }

    /** @dataProvider invalidPackages */
    public function testUnsafeOrIncompatiblePackageIsRejected(array $changes, array $extra): void
    {
        $this->expectException(Throwable::class);
        $this->packages->prepare($this->archive($changes, $extra));
    }

    public function testRechecksCatalogInsideInstallLockAndDoesNotOverwrite(): void
    {
        $first = $this->packages->prepare($this->archive());
        $second = $this->packages->prepare($this->archive(['id' => 'other-theme']));
        $installed = $this->packages->install($first['token'], $first['sha256']);
        $hash = hash_file('sha256', $installed->path . '/theme.json');
        try {
            $this->packages->install($second['token'], $second['sha256']);
            self::fail('Concurrent namespace collision accepted');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('already exists', $e->getMessage());
        }
        self::assertSame($hash, hash_file('sha256', $installed->path . '/theme.json'));
        self::assertDirectoryDoesNotExist($this->dir . '/packages/installed/other-theme');
    }

    public function testActivationRejectsAnIncompatibleThemeWithoutChangingSelection(): void
    {
        $this->manager()->activate('alpha');
        $preview = $this->packages->prepare($this->archive());
        $theme = $this->packages->install($preview['token'], $preview['sha256']);
        $data = json_decode(file_get_contents($theme->path . '/theme.json'), true, 32, JSON_THROW_ON_ERROR);
        $data['mcms'] = '^999';
        file_put_contents($theme->path . '/theme.json', json_encode($data, JSON_THROW_ON_ERROR));
        $fresh = $this->manager();
        try {
            $fresh->activate('theme-probe');
            self::fail('Incompatible theme activated');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('requires mCMS', $e->getMessage());
        }
        self::assertSame('alpha', $fresh->activeId());
    }

    public function testExistingStagingDirectoryIsNeverRemovedOnFailure(): void
    {
        $preview = $this->packages->prepare($this->archive());
        $stage = $this->dir . '/packages/pending/' . $preview['token'];
        mkdir($stage);
        file_put_contents($stage . '/marker', 'preserve');
        try {
            $this->packages->install($preview['token'], $preview['sha256']);
            self::fail('Existing staging directory accepted');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('already exists', $e->getMessage());
        }
        self::assertSame('preserve', file_get_contents($stage . '/marker'));
    }

    public function testChecksumExpirationCancellationAndInvalidTokenCannotChangeFiles(): void
    {
        $preview = $this->packages->prepare($this->archive());
        $path = $this->dir . '/packages/pending/' . $preview['token'] . '.zip';
        foreach (['checksum', 'expired', 'cancelled', 'invalid-token'] as $case) {
            if ($case === 'expired') {
                touch($path, time() - 3601);
            } elseif ($case === 'cancelled') {
                $this->packages->cancel($preview['token']);
            }
            try {
                $this->packages->install($case === 'invalid-token' ? '../installed' : $preview['token'], $case === 'checksum' ? str_repeat('0', 64) : $preview['sha256']);
                self::fail('Invalid preview accepted');
            } catch (RuntimeException) {
                self::assertDirectoryExists($this->dir . '/packages/installed');
            }
        }
        self::assertSame([], glob($this->dir . '/packages/installed/*'));
    }
}
