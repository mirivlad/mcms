<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Core\Navigation\NavigableResourceRegistry;
use MCMS\Modules\Pages\Api\PageRepositoryInterface;

final class PagesModuleTest extends CIUnitTestCase
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

        parent::tearDown();
    }

    public function testPagesEnablesMediaAndPublishesSanitizedContent(): void
    {
        service('language')->setLocale('en');

        $admin = new AdminRegistry();
        $services = new ModuleServiceRegistry();
        $navigation = new NavigableResourceRegistry();
        $manager = new ModuleManager(
            $this->db,
            $admin,
            $services,
            ROOTPATH . 'modules',
            $navigation,
        );

        self::assertArrayHasKey('media', $manager->catalog());
        self::assertArrayHasKey('pages', $manager->catalog());

        service('language')->setLocale('ru');
        self::assertSame('Страницы', lang('Pages.Admin.menuLabel'));
        self::assertSame('Создание страниц.', lang('Pages.Permissions.create'));
        service('language')->setLocale('en');
        self::assertFalse($this->db->tableExists('media_files'));
        self::assertFalse($this->db->tableExists('pages'));

        $manager->enable('pages');

        self::assertTrue($this->db->tableExists('media_files'));
        self::assertTrue($this->db->tableExists('pages'));
        self::assertSame(1, (int) $manager->states()['media']['enabled']);
        self::assertSame(1, (int) $manager->states()['pages']['enabled']);
        self::assertSame('draft', setting('pages.default_status'));

        $manager->bootEnabled();

        self::assertTrue($services->has('media.library'));
        self::assertTrue($services->has('pages.repository'));
        self::assertContains('media', array_column($admin->menu(), 'id'));
        self::assertContains('pages', array_column($admin->menu(), 'id'));
        self::assertTrue($navigation->has('pages.page'));
        self::assertSame('Pages', $navigation->get('pages.page')->label());

        $repository = $services->get('pages.repository');
        self::assertInstanceOf(PageRepositoryInterface::class, $repository);

        $page = $repository->create([
            'title' => 'Привет, мир!',
            'slug' => '',
            'body' => '<section><p>Hello <strong>world</strong>.</p><script>alert(1)</script><img src="/ok.png" onerror="alert(2)"></section>',
            'status' => 'published',
            'seo_title' => 'SEO title',
            'seo_description' => 'SEO description',
        ], 1);

        self::assertSame('privet-mir', $page['slug']);
        self::assertSame('/privet-mir', $page['url']);
        self::assertSame('published', $page['status']);
        self::assertSame([
            'id' => (string) $page['id'],
            'label' => 'Привет, мир!',
            'url' => '/privet-mir',
        ], $navigation->resolve('pages.page', (string) $page['id']));
        self::assertStringContainsString('<strong>world</strong>', $page['body']);
        self::assertStringNotContainsString('<script', $page['body']);
        self::assertStringNotContainsString('onerror', $page['body']);

        $published = $repository->findPublishedBySlug('privet-mir');
        self::assertNotNull($published);
        self::assertSame($page['id'], $published['id']);

        $publishedAt = $page['published_at'];
        $updated = $repository->update($page['id'], [
            'title' => 'Привет, мир — обновлено',
            'slug' => 'privet-mir',
            'body' => '<p>Updated</p>',
            'status' => 'published',
            'seo_title' => '',
            'seo_description' => '',
        ], 1);

        self::assertSame($publishedAt, $updated['published_at']);

        $manager->disable('pages');

        self::assertSame(0, (int) $manager->states()['pages']['enabled']);
        self::assertTrue($this->db->tableExists('pages'));
        self::assertSame(1, $this->db->table('pages')->countAllResults());

        $manager->enable('pages');

        self::assertSame(1, (int) $manager->states()['pages']['enabled']);
        self::assertSame(1, $this->db->table('pages')->countAllResults());
    }

    public function testMediaCannotBeDisabledWhilePagesDependsOnIt(): void
    {
        $manager = new ModuleManager(
            $this->db,
            new AdminRegistry(),
            new ModuleServiceRegistry(),
            ROOTPATH . 'modules',
        );

        $manager->enable('pages');

        $this->expectException(RuntimeException::class);
        $manager->disable('media');
    }
}
