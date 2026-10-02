<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Core\Navigation\NavigableResourceRegistry;
use MCMS\Modules\Seo\Controllers\Sitemap;

final class SiteModulesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\\Settings'];

    protected function setUp(): void
    {
        $this->resetServices();
        CodeIgniter\Events\Events::removeAllListeners(MCMS\Core\Events\CoreEvents::RESOURCE_MOVED);
        parent::setUp();
    }

    protected function tearDown(): void
    {
        foreach (['Seo', 'Feedback', 'Galleries', 'News', 'Pages', 'Media'] as $module) {
            $this->migrations->setNamespace('MCMS\\Modules\\' . $module)->regress(0, 'tests');
        }
        CodeIgniter\Events\Events::removeAllListeners(MCMS\Core\Events\CoreEvents::RESOURCE_MOVED);
        parent::tearDown();
    }

    public function testSelectedHomeNeverPublishesDraftAndSurvivesDisable(): void
    {
        [$manager, $services] = $this->boot(['pages']);
        $pages = $services->get('pages.repository');
        $page = $pages->create(['title' => 'Welcome', 'body' => '<p>Home content</p>', 'status' => 'published']);
        service('settings')->set('pages.home_page_id', $page['id']);
        self::assertStringContainsString('Home content', $services->get('pages.home')->renderHome());
        $pages->update($page['id'], ['title' => 'Welcome', 'body' => '<p>Private</p>', 'status' => 'draft']);
        self::assertNull($services->get('pages.home')->renderHome());
        $manager->disable('pages');
        $fresh = new ModuleServiceRegistry();
        (new ModuleManager($this->db, new AdminRegistry(), $fresh, ROOTPATH . 'modules'))->bootEnabled();
        self::assertFalse($fresh->has('pages.home'));
        self::assertSame($page['id'], (int) setting('pages.home_page_id'));
    }

    public function testFeedbackValidationInboxAndDisablePreserveMessages(): void
    {
        [$manager, $services] = $this->boot(['feedback']);
        $repository = $services->get('feedback.repository');
        $id = $repository->create(['name' => 'Reader', 'email' => 'reader@example.test', 'message' => '<script>plain text</script>']);
        self::assertNull($repository->find($id)['read_at']);
        $repository->markRead($id);
        self::assertNotNull($repository->find($id)['read_at']);
        try {
            $repository->create(['name' => 'Reader', 'email' => 'invalid', 'message' => 'Message']);
            self::fail('Invalid email must be rejected.');
        } catch (InvalidArgumentException) {
            self::assertSame(1, $repository->count());
        }
        $html = service('mcmsThemes')->render('feedback.form', ['values' => ['message' => '<script>plain text</script>'], 'error' => null, 'sent' => false]);
        self::assertStringContainsString('&lt;script&gt;plain text&lt;/script&gt;', $html);
        $manager->disable('feedback');
        self::assertSame(1, $repository->count());
        $manager->enable('feedback');
        $repository->delete($id);
        self::assertSame(0, $repository->count());
    }

    public function testSitemapEnumeratesBeyondSearchLimitAndExcludesPrivateContent(): void
    {
        [, $services] = $this->boot(['pages', 'news', 'galleries', 'feedback', 'seo']);
        for ($i = 0; $i < 120; $i++) {
            $services->get('pages.repository')->create(['title' => 'Public ' . $i, 'body' => '', 'status' => 'published']);
        }
        $services->get('pages.repository')->create(['title' => 'Secret page', 'body' => '', 'status' => 'draft']);
        $services->get('news.repository')->create(['title' => 'Future article', 'body' => '', 'status' => 'published', 'published_at' => date('Y-m-d H:i:s', time() + 3600)]);
        $controller = new Sitemap();
        $controller->initController(service('request'), service('response'), service('logger'));
        $xml = $controller->index()->getBody();
        $document = new DOMDocument();
        self::assertTrue($document->loadXML($xml));
        self::assertSame(124, $document->getElementsByTagName('loc')->length);
        self::assertStringNotContainsString('secret-page', $xml);
        self::assertStringNotContainsString('future-article', $xml);
        self::assertStringContainsString('/public-119', $xml);
    }

    public function testRedirectsResolveCurrentResourceWithoutChainsOrHijackingReusedPaths(): void
    {
        [, $services] = $this->boot(['pages', 'seo']);
        $pages = $services->get('pages.repository');
        $page = $pages->create(['title' => 'Old', 'body' => '', 'status' => 'published']);
        $pages->update($page['id'], ['title' => 'New', 'slug' => 'new', 'body' => '', 'status' => 'published']);
        $pages->update($page['id'], ['title' => 'Latest', 'slug' => 'latest', 'body' => '', 'status' => 'published']);
        $redirects = $services->get('seo.redirects');
        self::assertSame('/latest', $redirects->target('/old'));
        self::assertSame('/latest', $redirects->target('/new'));
        $pages->update($page['id'], ['title' => 'Latest', 'slug' => 'latest', 'body' => '', 'status' => 'draft']);
        self::assertNull($redirects->target('/old'));
        $pages->update($page['id'], ['title' => 'Latest', 'slug' => 'latest', 'body' => '', 'status' => 'published']);
        $pages->create(['title' => 'Reused', 'slug' => 'old', 'body' => '', 'status' => 'published']);
        self::assertNull($redirects->target('/old'));
        self::assertSame('/latest', $redirects->target('/new'));
        $pages->delete($page['id']);
        self::assertNull($redirects->target('/new'));
    }

    public function testGalleryShortcodePreservesSourceAndFollowsPublicationLifecycle(): void
    {
        [$manager, $services] = $this->boot(['pages', 'news', 'galleries']);
        $galleries = $services->get('galleries.repository');
        $album = $galleries->create(['title' => '<Album>', 'status' => 'published']);
        $source = '<p>Before [gallery id="' . $album['id'] . '"] after</p>';
        $page = $services->get('pages.repository')->create(['title' => 'Embedded', 'body' => $source, 'status' => 'published']);
        $news = $services->get('news.repository')->create(['title' => 'Embedded news', 'body' => $source, 'status' => 'published']);
        $renderer = service('mcmsShortcodes');
        self::assertStringContainsString('data-gallery-id="' . $album['id'] . '"', $renderer->render($page['body']));
        self::assertStringContainsString('&lt;Album&gt;', $renderer->render($news['body']));
        self::assertSame($source, $services->get('pages.repository')->find($page['id'])['body']);
        service('settings')->set('pages.home_page_id', $page['id']);
        self::assertStringContainsString('data-gallery-id=', $services->get('pages.home')->renderHome());
        $galleries->update($album['id'], ['title' => 'Draft', 'status' => 'draft']);
        self::assertStringNotContainsString('data-gallery-id=', $renderer->render($source));
        $galleries->update($album['id'], ['title' => 'Updated', 'status' => 'published']);
        self::assertStringContainsString('Updated', $renderer->render($source));
        $manager->disable('galleries');
        $fresh = new ModuleServiceRegistry();
        (new ModuleManager($this->db, new AdminRegistry(), $fresh, ROOTPATH . 'modules'))->bootEnabled();
        self::assertStringNotContainsString('[gallery', (new MCMS\Core\Content\ShortcodeRenderer($fresh))->render($source));
        $manager->enable('galleries');
        self::assertStringContainsString('Updated', $renderer->render($source));
        $galleries->delete($album['id']);
        self::assertStringNotContainsString('data-gallery-id=', $renderer->render($source));
    }

    private function boot(array $modules): array
    {
        $services = new ModuleServiceRegistry();
        $navigation = new NavigableResourceRegistry();
        $manager = new ModuleManager($this->db, new AdminRegistry(), $services, ROOTPATH . 'modules', $navigation);
        Services::injectMock('mcmsModules', $manager);
        Services::injectMock('mcmsModuleServices', $services);
        Services::injectMock('mcmsNavigation', $navigation);
        foreach ($modules as $module) { $manager->enable($module); }
        $manager->bootEnabled();
        return [$manager, $services];
    }
}
