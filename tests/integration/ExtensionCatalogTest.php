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
use MCMS\Core\Packages\CatalogSchema;
use MCMS\Core\Packages\CatalogValidator;
use MCMS\Core\Packages\ExtensionCatalog;
use MCMS\Core\Packages\ExtensionRemoval;
use MCMS\Core\Packages\GitHubCatalogSource;
use MCMS\Core\Packages\PackageState;
use MCMS\Core\Themes\ThemeManager;
use MCMS\Core\Themes\ThemePackages;
use MCMS\Core\Themes\ThemeUpdates;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class ExtensionCatalogTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use \CodeIgniter\Test\FeatureTestTrait;
    use \CodeIgniter\Shield\Test\AuthenticationTesting;

    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\\Settings', 'CodeIgniter\\Shield'];
    private string $dir;
    private ModuleManager $modules;
    private ModulePackages $modulePackages;
    private ModuleUpdates $moduleUpdates;
    private ThemeManager $themes;
    private ThemePackages $themePackages;
    private ThemeUpdates $themeUpdates;
    private ModuleRuntime $runtime;
    private ExtensionCatalog $catalog;
    private array $responses;
    private int $requests = 0;
    private bool $offline = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/mcms-catalog-' . bin2hex(random_bytes(8));
        mkdir($this->dir);
        $this->runtime = new ModuleRuntime($this->dir . '/modules-data');
        $this->modules = new ModuleManager($this->db, new AdminRegistry(), new ModuleServiceRegistry(), ROOTPATH . 'modules',
            packagesPath: $this->dir . '/modules-data/installed', runtime: $this->runtime);
        $this->themes = new ThemeManager(service('settings'), ROOTPATH . 'tests/_support/FixtureThemes', packagesPath: $this->dir . '/themes-data');
        $this->modulePackages = new ModulePackages($this->modules, $this->dir . '/modules-data', $this->runtime);
        $this->themePackages = new ThemePackages($this->themes, $this->dir . '/themes-data', $this->runtime);
        $this->moduleUpdates = new ModuleUpdates($this->modules, $this->modulePackages, $this->runtime, $this->dir . '/modules-data');
        $this->themeUpdates = new ThemeUpdates($this->themes, $this->themePackages, $this->runtime, $this->dir . '/themes-data');
        $this->responses = [GitHubCatalogSource::BASE . 'index.json' => file_get_contents(ROOTPATH . 'catalog/index.json')];
        foreach (glob(ROOTPATH . 'catalog/*/*/*.zip') as $file) {
            $this->responses[GitHubCatalogSource::BASE . substr($file, strlen(ROOTPATH . 'catalog/'))] = file_get_contents($file);
        }
        $this->catalog = new ExtensionCatalog($this->dir . '/modules-data/catalog', $this->dir . '/modules-data', $this->dir . '/themes-data',
            $this->modules, $this->themes, $this->modulePackages, $this->themePackages, $this->moduleUpdates, $this->themeUpdates,
            function (string $url, int $limit): string {
                $this->requests++;
                if ($this->offline) {
                    throw new RuntimeException('GitHub unavailable (test)');
                }
                return $this->responses[$url] ?? throw new RuntimeException('Unknown test URL');
            });
    }

    protected function tearDown(): void
    {
        $this->db->table(config('Migrations')->table)->where('namespace', 'MCMS\\Modules\\LifecycleDemo')->delete();
        \Config\Database::forge($this->db)->dropTable('lifecycle_demo_events', true);
        \Config\Database::forge($this->db)->dropTable('lifecycle_demo_notes', true);
        $this->runtime->release();
        PackageState::remove($this->dir);
        parent::tearDown();
    }

    private function removal(): ExtensionRemoval
    {
        return new ExtensionRemoval($this->db, $this->modules, $this->themes, $this->runtime, $this->dir . '/modules-data', $this->dir . '/themes-data');
    }

    private function publishNewModule(): void
    {
        $path = $this->dir . '/new.zip';
        ModulePackages::pack(ROOTPATH . 'examples/modules/lifecycle-demo', $path);
        $zip = new ZipArchive();
        $zip->open($path);
        $manifest = json_decode($zip->getFromName('module.json'), true);
        $manifest['version'] = '1.1.0';
        $manifest['cleanup']['tables'] = ['lifecycle_demo_events', 'lifecycle_demo_notes'];
        $zip->addFromString('module.json', json_encode($manifest));
        $zip->addFromString('Database/Migrations/2026-10-16-000002_CreateDemoEvents.php', <<<'PHP'
<?php
namespace MCMS\Modules\LifecycleDemo\Database\Migrations;
final class CreateDemoEvents extends \CodeIgniter\Database\Migration {
    public function up() {
        $this->forge->addField(['id' => ['type' => 'INTEGER', 'auto_increment' => true]]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('lifecycle_demo_events');
    }
    public function down() { $this->forge->dropTable('lifecycle_demo_events', true); }
}
PHP);
        $zip->close();
        $url = GitHubCatalogSource::BASE . 'modules/lifecycle-demo/lifecycle-demo-1.1.0.zip';
        $this->responses[$url] = file_get_contents($path);
        $index = json_decode($this->responses[GitHubCatalogSource::BASE . 'index.json'], true);
        foreach ($index['extensions'] as &$entry) {
            if ($entry['id'] === 'lifecycle-demo') {
                $entry['version'] = '1.1.0';
                $entry['versions'][] = [...$entry['versions'][0], 'version' => '1.1.0', 'download' => $url,
                    'bytes' => filesize($path), 'sha256' => hash_file('sha256', $path)];
            }
        }
        unset($entry);
        $this->responses[GitHubCatalogSource::BASE . 'index.json'] = json_encode($index);
        PackageState::write($this->dir . '/modules-data/catalog/status.json', ['attempted_at' => 0]);
        $this->catalog->refresh(true);
    }

    public function testThemePreviewRendersThroughExistingFormAndPermissionsGuardDownloads(): void
    {
        foreach (['mcmsExtensionCatalog' => $this->catalog, 'mcmsModules' => $this->modules,
            'mcmsThemes' => $this->themes, 'mcmsModuleRuntime' => $this->runtime,
            'mcmsModulePackages' => $this->modulePackages, 'mcmsThemePackages' => $this->themePackages] as $name => $service) {
            \Config\Services::injectMock($name, $service);
        }
        $owner = service('mcmsInstaller')->createSuperadmin('catalog-owner@example.test', 'catalog.owner', 'Catalog-Owner-2026!', locale: 'en');
        $this->catalog->refresh();
        csrf_hash();
        $this->actingAs($owner)->withSession()->post('/admin/extensions/mcms-atelier/prepare', ['version' => '1.1.1', csrf_token() => csrf_hash()])->assertRedirect();
        $this->withSession()->post('/admin/extensions/refresh', ['type' => 'module', csrf_token() => csrf_hash()])
            ->assertRedirectTo('/admin/extensions?type=module');
        $response = $this->withSession()->get('/admin/themes/package');
        $response->assertOK();
        self::assertStringContainsString('1.1.1', $response->response()->getBody());
        self::assertGreaterThan(0, session()->get('theme_package_preview')['templates']);
        self::assertGreaterThan(0, session()->get('theme_package_preview')['locations']);
        $provider = auth()->getProvider();
        $provider->save(new \CodeIgniter\Shield\Entities\User(['username' => 'catalog.viewer', 'email' => 'viewer@example.test', 'password' => 'Viewer-2026-Test!']));
        $viewer = $provider->findById($provider->getInsertID());
        $requests = $this->requests;
        auth()->logout();
        $this->actingAs($viewer)->withSession()->post('/admin/extensions/lifecycle-demo/prepare', ['version' => '1.0.0', csrf_token() => csrf_hash()])->assertRedirect();
        self::assertSame($requests, $this->requests);
        $this->withSession()->get('/admin/themes/package')->assertRedirect();
        $viewer->addPermission('core.themes');
        auth()->logout();
        $this->actingAs($viewer)->withSession()->get('/admin/extensions?type=module')->assertOK();
        $this->withSession()->post('/admin/extensions/lifecycle-demo/prepare', ['version' => '1.0.0', csrf_token() => csrf_hash()])->assertRedirect();
        self::assertSame($requests, $this->requests);
    }

    public function testRepositoryCatalogValidatorAndIndexAgree(): void
    {
        self::assertSame(2, CatalogValidator::validate(rtrim(ROOTPATH, '/')));
        self::assertCount(2, CatalogSchema::index(file_get_contents(ROOTPATH . 'catalog/index.json'))['extensions']);
    }

    public function testCatalogModuleInstallationUpdateAndSameVersionUpdateRefusal(): void
    {
        $this->publishNewModule();
        $preview = $this->catalog->prepare('lifecycle-demo', '1.0.0');
        self::assertSame('install', $preview['action']);
        $this->modulePackages->install($preview['token'], $preview['sha256']);
        self::assertSame([], $this->modules->states());
        $this->modules->enable('lifecycle-demo');
        $this->modules->disable('lifecycle-demo');
        self::assertSame(1, $this->catalog->updateCount('module'));
        try {
            $this->catalog->prepare('lifecycle-demo', '1.0.0');
            self::fail('Same-version update accepted');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('newer', $e->getMessage());
        }
        $preview = $this->catalog->prepare('lifecycle-demo', '1.1.0');
        $state = $this->moduleUpdates->queue('lifecycle-demo', $preview['token'], $preview['sha256']);
        $this->moduleUpdates->begin('lifecycle-demo', $state['operation'], 'apply');
        $this->moduleUpdates->runWorker('lifecycle-demo', $state['operation'], 'apply');
        $this->moduleUpdates->finalize();
        self::assertTrue($this->db->tableExists('lifecycle_demo_events'));
        self::assertSame('1.1.0', $this->modules->states()['lifecycle-demo']['version']);
        self::assertSame(0, (int) $this->modules->states()['lifecycle-demo']['enabled']);
        self::assertSame(1, $this->db->table('lifecycle_demo_notes')->countAllResults());
        self::assertSame(0, $this->catalog->updateCount());
    }

    public function testUpdateCountAndDownloadsRespectDisabledReverseDependencies(): void
    {
        $this->publishNewModule();
        $preview = $this->catalog->prepare('lifecycle-demo', '1.0.0');
        $this->modulePackages->install($preview['token'], $preview['sha256']);
        mkdir($this->dir . '/modules-data/installed/CatalogDependent', 0700, true);
        $manifest = json_decode(file_get_contents(ROOTPATH . 'examples/modules/lifecycle-demo/module.json'), true);
        $manifest = array_replace($manifest, ['id' => 'catalog-dependent', 'namespace' => 'MCMS\\Modules\\CatalogDependent',
            'provider' => 'MCMS\\Modules\\CatalogDependent\\Module', 'requires' => [['id' => 'lifecycle-demo', 'version' => '~1.0.0']]]);
        file_put_contents($this->dir . '/modules-data/installed/CatalogDependent/module.json', json_encode($manifest));
        $this->db->table('mcms_modules')->insert(['module_id' => 'catalog-dependent', 'version' => '1.0.0', 'enabled' => 0,
            'installed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        self::assertSame(0, $this->catalog->updateCount('module'));
        $requests = $this->requests;
        try {
            $this->catalog->prepare('lifecycle-demo', '1.1.0');
            self::fail('Incompatible reverse dependency accepted');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('catalog-dependent', $e->getMessage());
        }
        self::assertSame($requests, $this->requests);
    }

    public function testCatalogRetainedReinstallSameAndNewVersionThenCleanPurge(): void
    {
        $this->publishNewModule();
        $preview = $this->catalog->prepare('lifecycle-demo', '1.0.0');
        $this->modulePackages->install($preview['token'], $preview['sha256']);
        $this->modules->enable('lifecycle-demo');
        $removal = $this->removal();
        $preview = $removal->preview('module', 'lifecycle-demo');
        $removal->remove('module', 'lifecycle-demo', false, $preview['fingerprint']);
        self::assertSame('retained', $this->catalog->entries()['lifecycle-demo']['state']);
        foreach (['1.0.0', '1.1.0'] as $version) {
            $preview = $this->catalog->prepare('lifecycle-demo', $version);
            $this->modulePackages->install($preview['token'], $preview['sha256']);
            $this->modules->enable('lifecycle-demo');
            self::assertSame(1, $this->db->table('lifecycle_demo_notes')->countAllResults());
            $preview = $removal->preview('module', 'lifecycle-demo');
            $removal->remove('module', 'lifecycle-demo', $version === '1.1.0', $preview['fingerprint'], true);
        }
        self::assertFalse($this->db->tableExists('lifecycle_demo_notes'));
        self::assertFalse($this->db->tableExists('lifecycle_demo_events'));
    }

    public function testAtelierCatalogInstallAndSafeUpdatePreservesSettings(): void
    {
        $this->catalog->refresh();
        $preview = $this->catalog->prepare('mcms-atelier', '1.1.1');
        $this->themePackages->install($preview['token'], $preview['sha256']);
        $this->themes->activate('mcms-atelier');
        $this->themes->saveAppearance('mcms-atelier', ['accent' => '#123456']);
        self::assertSame(1, $this->catalog->updateCount('theme'));
        $preview = $this->catalog->prepare('mcms-atelier', '1.1.2');
        $this->themeUpdates->apply('mcms-atelier', $preview['token'], $preview['sha256'], $preview['source']);
        self::assertSame('1.1.2', $this->themes->active()->version);
        self::assertSame('#123456', $this->themes->appearance()['accent']);
    }

    public function testOfflineAndInvalidRefreshPreserveCacheAndDoNotFetchOnReads(): void
    {
        $this->catalog->refresh();
        $checked = $this->catalog->cache()['checked_at'];
        $requests = $this->requests;
        $this->catalog->entries();
        $this->catalog->refresh();
        self::assertSame($requests, $this->requests);
        PackageState::write($this->dir . '/modules-data/catalog/status.json', ['attempted_at' => 0]);
        $this->offline = true;
        try {
            $this->catalog->refresh(true);
            self::fail('Offline error not reported');
        } catch (RuntimeException) {
        }
        self::assertSame($checked, $this->catalog->cache()['checked_at']);
        self::assertCount(2, $this->catalog->entries());
        self::assertStringContainsString('unavailable', $this->catalog->status()['error']);
        self::assertFalse($this->catalog->due());
    }

    public function testChecksumCorruptZipAndManifestMismatchAreRejected(): void
    {
        $this->catalog->refresh();
        $url = GitHubCatalogSource::BASE . 'modules/lifecycle-demo/lifecycle-demo-1.0.0.zip';
        $original = $this->responses[$url];
        $this->responses[$url] = 'corrupt';
        try {
            $this->catalog->prepare('lifecycle-demo', '1.0.0');
            self::fail('Bad checksum accepted');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('SHA-256', $e->getMessage());
        }
        // Even a checksum matching corrupt bytes cannot bypass ZIP inspection.
        $cache = $this->catalog->cache();
        $cache['extensions'][0]['versions'][0]['bytes'] = 7;
        $cache['extensions'][0]['versions'][0]['sha256'] = hash('sha256', 'corrupt');
        PackageState::write($this->dir . '/modules-data/catalog/cache.json', $cache);
        try {
            $this->catalog->prepare('lifecycle-demo', '1.0.0');
            self::fail('Corrupt archive accepted');
        } catch (RuntimeException) {
        }
        $this->responses[$url] = $original;
        $this->expectException(RuntimeException::class);
        CatalogSchema::assertPackage(['id' => 'wrong', 'type' => 'module'], ['version' => '1.0.0', 'mcms' => '^0.1', 'requires' => []],
            ModulePackages::inspectArchive(ROOTPATH . 'catalog/modules/lifecycle-demo/lifecycle-demo-1.0.0.zip')['manifest']);
    }
}
