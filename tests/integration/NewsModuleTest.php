<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Core\Navigation\NavigableResourceRegistry;
use MCMS\Modules\News\Api\NewsRepositoryInterface;

final class NewsModuleTest extends CIUnitTestCase
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
            ->setNamespace('MCMS\\Modules\\News')
            ->regress(0, 'tests');

        $this->migrations
            ->setNamespace('MCMS\\Modules\\Media')
            ->regress(0, 'tests');

        parent::tearDown();
    }

    public function testNewsPublishesCurrentArticlesAndExposesNavigationProviders(): void
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

        self::assertArrayHasKey('news', $manager->catalog());
        $manager->enable('news');

        self::assertTrue($this->db->tableExists('media_files'));
        self::assertTrue($this->db->tableExists('news_articles'));
        self::assertSame(1, (int) $manager->states()['media']['enabled']);
        self::assertSame(1, (int) $manager->states()['news']['enabled']);

        $manager->bootEnabled();

        self::assertTrue($services->has('news.repository'));
        self::assertContains('news', array_column($admin->menu(), 'id'));
        self::assertTrue($navigation->has('news.archive'));
        self::assertTrue($navigation->has('news.article'));
        self::assertSame('/news', $navigation->resolve('news.archive', 'index')['url']);

        $repository = $services->get('news.repository');
        self::assertInstanceOf(NewsRepositoryInterface::class, $repository);

        $article = $repository->create([
            'title' => 'Первая новость',
            'slug' => '',
            'excerpt' => '',
            'body' => '<p>Hello <strong>news</strong>.</p><script>alert(1)</script>',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s', time() - 60),
            'featured_media_id' => '',
            'seo_title' => 'News SEO',
            'seo_description' => 'News description',
        ], 1);

        self::assertSame('pervaya-novost', $article['slug']);
        self::assertSame('/news/pervaya-novost', $article['url']);
        self::assertTrue($article['is_live']);
        self::assertStringContainsString('Hello news.', $article['excerpt']);
        self::assertStringNotContainsString('<script', $article['body']);
        self::assertSame(
            '/news/pervaya-novost',
            $navigation->resolve('news.article', (string) $article['id'])['url'],
        );

        $scheduled = $repository->create([
            'title' => 'Future article',
            'slug' => 'future-article',
            'excerpt' => 'Later',
            'body' => '<p>Later</p>',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s', time() + 3600),
            'featured_media_id' => '',
            'seo_title' => '',
            'seo_description' => '',
        ], 1);

        self::assertFalse($scheduled['is_live']);
        self::assertNull($repository->findPublishedBySlug('future-article'));
        self::assertNull($navigation->resolve('news.article', (string) $scheduled['id']));
        self::assertCount(1, $repository->listPublished());
        self::assertSame(1, $repository->countPublished());

        $manager->disable('news');
        self::assertTrue($this->db->tableExists('news_articles'));
        self::assertSame(2, $this->db->table('news_articles')->countAllResults());

        $manager->enable('news');
        self::assertSame(2, $this->db->table('news_articles')->countAllResults());
    }

    public function testMediaCannotBeDisabledWhileNewsDependsOnIt(): void
    {
        $manager = new ModuleManager(
            $this->db,
            new AdminRegistry(),
            new ModuleServiceRegistry(),
            ROOTPATH . 'modules',
        );

        $manager->enable('news');

        $this->expectException(RuntimeException::class);
        $manager->disable('media');
    }
}
