<?php

declare(strict_types=1);

use App\Services\DemoData;
use CodeIgniter\Settings\Settings;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Core\Navigation\NavigableResourceRegistry;

final class DemoDataTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\\Settings', 'CodeIgniter\\Shield'];
    private string|false $previousFlag;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();
        $this->previousFlag = getenv('MCMS_DEMO_SEED');
        putenv('MCMS_DEMO_SEED');
    }

    protected function tearDown(): void
    {
        service('mcmsModules')->catalog();
        foreach (['Seo', 'Catalogue', 'ProductImages', 'News', 'Pages', 'Media'] as $module) {
            $this->migrations->setNamespace('MCMS\\Modules\\' . $module)->regress(0, 'tests');
        }
        $this->previousFlag === false ? putenv('MCMS_DEMO_SEED') : putenv('MCMS_DEMO_SEED=' . $this->previousFlag);
        $this->resetServices();
        parent::tearDown();
    }

    private function install(): void
    {
        service('mcmsInstaller')->createSuperadmin('demo-owner@example.test', 'demo.owner', 'Demo-Owner-2026!', 'Original site', false, 'en');
    }

    private function marker(): mixed
    {
        return (new Settings(config('Settings')))->get(DemoData::MARKER);
    }

    private function enable(array $ids): ModuleServiceRegistry
    {
        $services = new ModuleServiceRegistry();
        $modules = new ModuleManager($this->db, new AdminRegistry(), $services, ROOTPATH . 'modules', new NavigableResourceRegistry());
        foreach ($ids as $id) {
            $modules->enable($id);
        }
        $modules->bootEnabled();
        return $services;
    }

    public function testDefaultAndOtherTruthyValuesNeverEnableDemoContent(): void
    {
        $this->install();
        $seed = new DemoData($this->db);
        foreach ([null, '0', 'true', 'yes', 'false', '1 '] as $flag) {
            $flag === null ? putenv('MCMS_DEMO_SEED') : putenv('MCMS_DEMO_SEED=' . $flag);
            self::assertSame('disabled', $seed->seed());
            self::assertSame(0, $this->db->table('mcms_modules')->countAllResults());
            self::assertFalse($this->db->tableExists('pages'));
            self::assertNull($this->marker());
        }
    }

    public function testFreshInstallationDefersUntilAnOwnerExistsAndSeedsOnlyOnce(): void
    {
        putenv('MCMS_DEMO_SEED=1');
        $seed = new DemoData($this->db);
        self::assertSame('waiting-for-installation', $seed->seed());
        self::assertNull($this->marker());
        $this->install();
        self::assertSame('completed', $seed->seed());
        self::assertSame(5, $this->db->table('pages')->countAllResults());
        self::assertSame(6, $this->db->table('news_articles')->countAllResults());
        self::assertSame(12, $this->db->table('catalogue_products')->countAllResults());
        self::assertSame(6, $this->db->table('catalogue_categories')->countAllResults());
        self::assertSame(2, $this->db->table('seo_redirects')->countAllResults());
        self::assertSame(5, $this->db->table('news_articles')->where('status', 'published')->countAllResults());
        self::assertSame(11, $this->db->table('catalogue_products')->where('status', 'published')->countAllResults());
        $marker = $this->marker();
        self::assertNotNull($marker);
        self::assertSame('Original site', setting('Core.siteName'));
        self::assertFalse(setting('Auth.allowRegistration'));
        // Simulate manual edits and deletion between redeploys. Neither is undone.
        $this->db->table('pages')->where('slug', 'demo-about')->update(['title' => 'My edited page']);
        $this->db->table('news_articles')->where('slug', 'demo-draft')->delete();
        $this->db->table('mcms_modules')->where('module_id', 'catalogue')->update(['enabled' => 0]);
        $this->resetServices();
        self::assertSame('already-completed', (new DemoData($this->db))->seed());
        self::assertSame('My edited page', $this->db->table('pages')->where('slug', 'demo-about')->get()->getRowArray()['title']);
        self::assertSame(5, $this->db->table('news_articles')->countAllResults());
        self::assertSame(12, $this->db->table('catalogue_products')->countAllResults());
        self::assertSame($marker, $this->marker());
        self::assertSame(0, (int) $this->db->table('mcms_modules')->where('module_id', 'catalogue')->get()->getRowArray()['enabled']);
    }

    public function testExistingPagesAndProductsAreNeverReplaced(): void
    {
        $this->install();
        $services = $this->enable(['pages', 'catalogue']);
        $page = $services->get('pages.repository')->create(['title' => 'Real page', 'slug' => 'demo-about', 'body' => '<p>Original content</p>', 'status' => 'draft']);
        $userId = (int) $this->db->table('auth_groups_users')->get()->getRowArray()['user_id'];
        $product = $services->get('catalogue.repository')->save(null, ['title' => 'Real product', 'slug' => 'demo-notebook',
            'body' => '<p>Original product</p>', 'status' => 'draft', 'image_set_id' => $services->get('product-images.library')->createSet($userId),
            'variants' => [['sku' => 'REAL-PRODUCT', 'price' => '888.88']]], $userId);
        putenv('MCMS_DEMO_SEED=1');
        self::assertSame('completed', (new DemoData($this->db))->seed());
        self::assertSame($page, $services->get('pages.repository')->find($page['id']));
        self::assertSame($product, $services->get('catalogue.repository')->find($product['id']));
        self::assertSame(5, $this->db->table('pages')->countAllResults());
        self::assertSame(12, $this->db->table('catalogue_products')->countAllResults());
        // A draft target must not gain a public redirect.
        self::assertSame(1, $this->db->table('seo_redirects')->countAllResults());
    }

    public function testFailureRollsBackContentAndCompletionMarkerBeforeRetry(): void
    {
        $this->install();
        $services = $this->enable(['catalogue']);
        $userId = (int) $this->db->table('auth_groups_users')->get()->getRowArray()['user_id'];
        $real = $services->get('catalogue.repository')->save(null, ['title' => 'Existing SKU owner', 'slug' => 'real-product',
            'status' => 'published', 'image_set_id' => $services->get('product-images.library')->createSet($userId),
            'variants' => [['sku' => 'MCMS-DEMO-CERAMIC-MUG-1', 'price' => '100']]], $userId);
        putenv('MCMS_DEMO_SEED=1');
        try {
            (new DemoData($this->db))->seed();
            self::fail('A conflicting SKU was overwritten');
        } catch (InvalidArgumentException) {
            self::assertNull($this->marker());
            self::assertSame(0, $this->db->table('pages')->countAllResults());
            self::assertSame(0, $this->db->table('news_articles')->countAllResults());
            self::assertSame(0, $this->db->table('catalogue_categories')->countAllResults());
            self::assertSame(1, $this->db->table('catalogue_products')->countAllResults());
            self::assertSame(1, $this->db->table('product_image_sets')->countAllResults());
            self::assertSame($real, $services->get('catalogue.repository')->find($real['id']));
        }
        $this->db->table('catalogue_variants')->where('product_id', $real['id'])->update(['sku' => 'REAL-SKU']);
        self::assertSame('completed', (new DemoData($this->db))->seed());
        self::assertSame(13, $this->db->table('catalogue_products')->countAllResults());
        self::assertNotNull($this->marker());
    }
}
