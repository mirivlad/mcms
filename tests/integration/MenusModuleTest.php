<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Core\Navigation\NavigableResourceRegistry;
use MCMS\Core\Themes\MenuSourceInterface;
use MCMS\Modules\Menus\Api\MenuRepositoryInterface;

final class MenusModuleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = [
        'App',
        'CodeIgniter\\Settings',
    ];

    protected function tearDown(): void
    {
        $this->migrations
            ->setNamespace('MCMS\\Modules\\Pages')
            ->regress(0, 'tests');

        $this->migrations
            ->setNamespace('MCMS\\Modules\\Media')
            ->regress(0, 'tests');

        $this->migrations
            ->setNamespace('MCMS\\Modules\\Menus')
            ->regress(0, 'tests');

        parent::tearDown();
    }

    public function testMenusInstallsBuildsTreesAndPreservesDataAcrossDisable(): void
    {
        service('language')->setLocale('en');

        $admin = new AdminRegistry();
        $services = new ModuleServiceRegistry();
        $manager = new ModuleManager(
            $this->db,
            $admin,
            $services,
            ROOTPATH . 'modules',
        );

        self::assertArrayHasKey('menus', $manager->catalog());
        self::assertFalse($this->db->tableExists('menus'));
        self::assertFalse($this->db->tableExists('menu_items'));

        service('language')->setLocale('ru');
        self::assertSame('Меню', lang('Menus.Admin.menuLabel'));
        self::assertSame('Создание, редактирование, удаление и сортировка меню.', lang('Menus.Permissions.manage'));
        service('language')->setLocale('en');

        $manager->enable('menus');

        self::assertTrue($this->db->tableExists('menus'));
        self::assertTrue($this->db->tableExists('menu_items'));
        self::assertSame(1, (int) $manager->states()['menus']['enabled']);

        $manager->bootEnabled();

        self::assertTrue($services->has('menus.repository'));
        self::assertContains('menus', array_column($admin->menu(), 'id'));

        $repository = $services->get('menus.repository');
        self::assertInstanceOf(MenuRepositoryInterface::class, $repository);

        $menu = $repository->createMenu([
            'name' => 'Главное меню',
            'handle' => '',
            'description' => 'Primary navigation',
        ]);

        self::assertSame('glavnoe-menyu', $menu['handle']);
        self::assertSame($menu['id'], $repository->findByHandle('glavnoe-menyu')['id']);

        $home = $repository->saveItem($menu['id'], null, [
            'label' => 'Home',
            'target_type' => 'custom',
            'target' => '/',
            'parent_id' => null,
            'enabled' => true,
            'open_in_new' => false,
        ]);

        $about = $repository->saveItem($menu['id'], null, [
            'label' => 'About',
            'target_type' => 'custom',
            'target' => '/about',
            'parent_id' => null,
            'enabled' => true,
            'open_in_new' => false,
        ]);

        $team = $repository->saveItem($menu['id'], null, [
            'label' => 'Team',
            'target_type' => 'custom',
            'target' => '/team',
            'parent_id' => $about['id'],
            'enabled' => true,
            'open_in_new' => false,
        ]);

        $external = $repository->saveItem($menu['id'], null, [
            'label' => 'External',
            'target_type' => 'custom',
            'target' => 'https://example.test/',
            'parent_id' => null,
            'enabled' => false,
            'open_in_new' => true,
        ]);

        $tree = $repository->tree($menu['id']);
        self::assertCount(3, $tree);
        self::assertSame('Home', $tree[0]['label']);
        self::assertSame('About', $tree[1]['label']);
        self::assertSame('Team', $tree[1]['children'][0]['label']);
        self::assertFalse($tree[2]['enabled']);
        self::assertTrue($tree[2]['open_in_new']);

        $publicTree = $repository->treeByHandle('glavnoe-menyu', true);
        self::assertCount(2, $publicTree);
        self::assertSame('/about', $publicTree[1]['url']);

        $repository->updateStructure($menu['id'], [
            ['id' => $about['id'], 'parent_id' => null, 'position' => 10],
            ['id' => $team['id'], 'parent_id' => $about['id'], 'position' => 10],
            ['id' => $home['id'], 'parent_id' => null, 'position' => 20],
            ['id' => $external['id'], 'parent_id' => null, 'position' => 30],
        ]);

        $tree = $repository->tree($menu['id']);
        self::assertSame('About', $tree[0]['label']);
        self::assertSame('Home', $tree[1]['label']);

        try {
            $repository->saveItem($menu['id'], $about['id'], [
                'label' => 'About',
                'target_type' => 'custom',
                'target' => '/about',
                'parent_id' => $team['id'],
                'enabled' => true,
                'open_in_new' => false,
            ]);
            self::fail('Circular nesting must be rejected.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }

        $manager->disable('menus');

        self::assertSame(0, (int) $manager->states()['menus']['enabled']);
        self::assertTrue($this->db->tableExists('menus'));
        self::assertTrue($this->db->tableExists('menu_items'));
        self::assertSame(1, $this->db->table('menus')->countAllResults());
        self::assertSame(4, $this->db->table('menu_items')->countAllResults());

        $manager->enable('menus');
        self::assertSame(1, (int) $manager->states()['menus']['enabled']);
        self::assertSame(4, $this->db->table('menu_items')->countAllResults());
    }

    public function testInternalResourcesResolveWithoutMenusDependingOnPages(): void
    {
        service('language')->setLocale('en');

        $services = new ModuleServiceRegistry();
        $navigation = new NavigableResourceRegistry();
        $manager = new ModuleManager(
            $this->db,
            new AdminRegistry(),
            $services,
            ROOTPATH . 'modules',
            $navigation,
        );

        $manager->enable('pages');
        $manager->enable('menus');
        $manager->bootEnabled();

        self::assertTrue($navigation->has('pages.page'));

        $pages = $services->get('pages.repository');
        $page = $pages->create([
            'title' => 'About us',
            'slug' => 'about-us',
            'body' => '<p>About</p>',
            'status' => 'published',
            'seo_title' => '',
            'seo_description' => '',
        ]);

        $menus = $services->get('menus.repository');
        self::assertInstanceOf(MenuRepositoryInterface::class, $menus);

        $menu = $menus->createMenu([
            'name' => 'Primary',
            'handle' => 'primary',
            'description' => '',
        ]);
        $item = $menus->saveItem($menu['id'], null, [
            'label' => 'About',
            'target_type' => 'pages.page',
            'target' => (string) $page['id'],
            'enabled' => true,
            'open_in_new' => false,
        ]);

        self::assertSame('pages.page', $item['target_type']);
        self::assertSame((string) $page['id'], $item['target']);
        self::assertSame('/about-us', $item['url']);
        self::assertSame('About us', $item['resource_label']);
        self::assertTrue($item['target_available']);

        $manager->disable('pages');

        $servicesWithoutPages = new ModuleServiceRegistry();
        $navigationWithoutPages = new NavigableResourceRegistry();
        $nextRequest = new ModuleManager(
            $this->db,
            new AdminRegistry(),
            $servicesWithoutPages,
            ROOTPATH . 'modules',
            $navigationWithoutPages,
        );
        $nextRequest->bootEnabled();

        self::assertFalse($navigationWithoutPages->has('pages.page'));
        $menusWithoutPages = $servicesWithoutPages->get('menus.repository');
        $preserved = $menusWithoutPages->findItem($menu['id'], $item['id']);
        self::assertNotNull($preserved);
        self::assertSame('pages.page', $preserved['target_type']);
        self::assertSame((string) $page['id'], $preserved['target']);
        self::assertNull($preserved['url']);
        self::assertFalse($preserved['target_available']);

        $nextRequest->enable('pages');

        $servicesRestored = new ModuleServiceRegistry();
        $navigationRestored = new NavigableResourceRegistry();
        $restoredRequest = new ModuleManager(
            $this->db,
            new AdminRegistry(),
            $servicesRestored,
            ROOTPATH . 'modules',
            $navigationRestored,
        );
        $restoredRequest->bootEnabled();

        self::assertTrue($navigationRestored->has('pages.page'));
        $restored = $servicesRestored->get('menus.repository')->findItem($menu['id'], $item['id']);
        self::assertSame('/about-us', $restored['url']);
        self::assertTrue($restored['target_available']);
    }

    public function testMenusRejectsUnsafeCustomTargetsAndDeletesSubtrees(): void
    {
        $services = new ModuleServiceRegistry();
        $manager = new ModuleManager(
            $this->db,
            new AdminRegistry(),
            $services,
            ROOTPATH . 'modules',
        );

        $manager->enable('menus');
        $manager->bootEnabled();

        $repository = $services->get('menus.repository');
        self::assertInstanceOf(MenuRepositoryInterface::class, $repository);

        $menu = $repository->createMenu([
            'name' => 'Footer',
            'handle' => 'footer',
            'description' => '',
        ]);

        $parent = $repository->saveItem($menu['id'], null, [
            'label' => 'Group',
            'target_type' => 'custom',
            'target' => '',
            'enabled' => true,
            'open_in_new' => false,
        ]);

        $child = $repository->saveItem($menu['id'], null, [
            'label' => 'Child',
            'target_type' => 'custom',
            'target' => '#child',
            'parent_id' => $parent['id'],
            'enabled' => true,
            'open_in_new' => false,
        ]);

        try {
            $repository->saveItem($menu['id'], null, [
                'label' => 'Unsafe',
                'target_type' => 'custom',
                'target' => 'javascript:alert(1)',
                'enabled' => true,
                'open_in_new' => false,
            ]);
            self::fail('Unsafe target must be rejected.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }

        $repository->deleteItem($menu['id'], $parent['id']);

        self::assertNull($repository->findItem($menu['id'], $parent['id']));
        self::assertNull($repository->findItem($menu['id'], $child['id']));
    }
}