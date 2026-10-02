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
        self::assertSame('Before after', $news['excerpt']);
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

    public function testSignatureThemeRendersPublicTemplatesAndSwitchesWithoutChangingContent(): void
    {
        [$manager, $services] = $this->boot(['pages', 'news', 'galleries', 'feedback']);
        $themes = service('mcmsThemes');
        $themes->activate('mcms-signature');
        $album = $services->get('galleries.repository')->create(['title' => 'Signature album', 'status' => 'published']);
        $source = '<p>Signature content [gallery id="' . $album['id'] . '"]</p>';
        $page = $services->get('pages.repository')->create(['title' => '<Title>', 'body' => $source, 'status' => 'published']);
        $article = $services->get('news.repository')->create(['title' => 'Signature article', 'body' => $source, 'status' => 'published']);
        $page['body'] = service('mcmsShortcodes')->render($page['body']);
        $article['body'] = service('mcmsShortcodes')->render($article['body']);
        foreach ([
            'home' => [], 'page' => ['page' => $page], 'news.article' => ['article' => $article],
            'news.index' => ['articles' => [$article], 'page' => 1, 'lastPage' => 1],
            'galleries.index' => ['albums' => [$album], 'page' => 1, 'lastPage' => 1],
            'galleries.album' => ['album' => $album],
            'feedback.form' => ['values' => [], 'sent' => false, 'error' => null],
        ] as $template => $data) {
            $html = $themes->render($template, $data);
            self::assertStringContainsString('data-theme="mcms-signature"', $html, $template);
            self::assertSame(1, preg_match_all('/<h1\b/', $html), $template);
            self::assertStringNotContainsString('Signature.', $html, $template);
            self::assertStringContainsString('href="#main-content"', $html, $template);
        }
        self::assertStringContainsString('&lt;Title&gt;', $themes->render('page', ['page' => $page]));
        self::assertStringContainsString('sig-gallery-embed', $page['body']);
        $themes->activate('mcms-default');
        self::assertStringContainsString('mcms-gallery-embed', service('mcmsShortcodes')->render($source));
        self::assertSame(1, preg_match_all('/<h1\b/', $themes->render('feedback.form', ['values' => [], 'sent' => false, 'error' => null])));
        self::assertSame($source, $services->get('pages.repository')->find($page['id'])['body']);
        $manager->disable('news');
        $manager->disable('galleries');
        $manager->disable('feedback');
        $fresh = new ModuleServiceRegistry();
        $navigation = new NavigableResourceRegistry();
        (new ModuleManager($this->db, new AdminRegistry(), $fresh, ROOTPATH . 'modules', $navigation))->bootEnabled();
        Services::injectMock('mcmsModuleServices', $fresh);
        Services::injectMock('mcmsNavigation', $navigation);
        $themes->activate('mcms-signature');
        $html = $themes->render('home');
        self::assertStringNotContainsString('href="' . site_url('news') . '"', $html);
        self::assertStringNotContainsString('href="' . site_url('galleries') . '"', $html);
    }

    public function testGalleryDisplayModesPreserveOrderEscapeCaptionsAndHandleMissingImages(): void
    {
        [, $services] = $this->boot(['pages', 'news', 'galleries']);
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $this->db->table('media_files')->insert([
                'original_name' => 'display-' . $i . '.png', 'storage_path' => 'gallery-display-tests/' . $i . '.png',
                'mime_type' => 'image/png', 'extension' => 'png', 'size_bytes' => 100, 'width' => 960, 'height' => 480,
                'alt_text' => 'Alt ' . $i, 'sha256' => str_repeat('a', 64), 'created_at' => date('Y-m-d H:i:s'),
            ]);
            $ids[] = (int) $this->db->insertID();
        }
        $albums = $services->get('galleries.repository');
        $album = $albums->create(['title' => '<Album>', 'status' => 'published', 'media_ids' => $ids,
            'positions' => [$ids[0] => 10, $ids[1] => 0, $ids[2] => 20],
            'captions' => [$ids[0] => 'Later', $ids[1] => '<script>Caption</script>', $ids[2] => 'Missing']]);
        $renderer = service('mcmsShortcodes');
        foreach (['mcms-default', 'mcms-signature'] as $theme) {
            service('mcmsThemes')->activate($theme);
            foreach (['slider', 'carousel', 'cards'] as $mode) {
                $source = '<p>Before [gallery view="' . $mode . '" id="' . $album['id'] . '"] after</p>';
                $html = $renderer->render($source);
                self::assertStringContainsString('mcms-gallery-' . $mode, $html);
                self::assertStringContainsString('&lt;script&gt;Caption&lt;/script&gt;', $html);
                self::assertLessThan(strpos($html, 'Later'), strpos($html, '&lt;script&gt;Caption'));
                self::assertStringContainsString('Before ', $html);
                self::assertStringContainsString(' after', $html);
                self::assertStringNotContainsString('<script>Caption', $html);
                self::assertStringNotContainsString('[gallery', $html);
            }
        }
        foreach (['view="unknown"', 'view="../slider"', 'extra="value"'] as $invalid) {
            self::assertSame('', $renderer->render('[gallery id="' . $album['id'] . '" ' . $invalid . ']'));
        }
        $services->get('media.library')->delete($ids[2], true);
        $slider = $renderer->render('[gallery id="' . $album['id'] . '" view="slider"]');
        $document = new DOMDocument();
        $document->loadHTML($slider);
        self::assertSame(2, (new DOMXPath($document))->query('//span[@data-slide]')->length);
        self::assertStringNotContainsString('Missing', $slider);
        self::assertStringContainsString(lang('Galleries.Public.missing'), $renderer->render('[gallery id="' . $album['id'] . '" view="cards"]'));
        foreach (['slider', 'carousel', 'cards'] as $mode) {
            $draft = $albums->update($album['id'], ['title' => 'Draft', 'status' => 'draft']);
            self::assertSame('', $renderer->render('[gallery id="' . $draft['id'] . '" view="' . $mode . '"]'));
        }
        $empty = $albums->create(['title' => 'Empty', 'status' => 'published']);
        self::assertStringContainsString(lang('Galleries.Public.noImages'), $renderer->render('[gallery id="' . $empty['id'] . '" view="slider"]'));
        $single = $albums->create(['title' => 'Single', 'status' => 'published', 'media_ids' => [$ids[0]]]);
        self::assertStringNotContainsString('data-slider-controls', $renderer->render('[gallery id="' . $single['id'] . '" view="slider"]'));
    }

    public function testMetadataRulesOverridesAndDisableAreIndependentOfContent(): void
    {
        [$manager, $services] = $this->boot(['pages', 'news', 'seo']);
        $metadata = $services->get('seo.metadata');
        $rules = $metadata->settings();
        $rules['page'] = ['title' => '{title} | {site_name}', 'description' => 'About {title}', 'keywords' => '', 'keywords_source' => 'tags'];
        $rules['news']['title'] = '{site_name}: {title}';
        $metadata->save($rules);
        $pages = $services->get('pages.repository');
        $page = $pages->create(['title' => 'Test title', 'body' => '<p>Text</p>', 'status' => 'published', 'tags' => 'CMS, Новости']);
        foreach (['mcms-default', 'mcms-signature'] as $theme) {
            service('mcmsThemes')->activate($theme);
            $html = service('mcmsThemes')->render('page', ['page' => $page, 'siteName' => 'My site']);
            self::assertStringContainsString('<title>Test title | My site</title>', $html);
            self::assertStringContainsString('name="description" content="About&#x20;Test&#x20;title"', $html);
            $decoded = html_entity_decode($html);
            self::assertStringContainsString('name="keywords" content="CMS, Новости"', $decoded);
            $manual = [...$page, 'seo_title' => 'Exact <title>', 'seo_description' => 'Manual desc', 'seo_keywords' => 'custom'];
            $html = service('mcmsThemes')->render('page', ['page' => $manual, 'siteName' => 'My site']);
            self::assertStringContainsString('<title>Exact &lt;title&gt;</title>', $html);
            self::assertStringContainsString('name="keywords" content="custom"', $html);
        }
        $before = $metadata->settings();
        $rules['news']['title'] = '{unsupported}';
        try {
            $metadata->save($rules);
            self::fail('Unknown placeholders must not be saved.');
        } catch (InvalidArgumentException) {
            self::assertSame($before, $metadata->settings());
        }
        $manager->disable('seo');
        $fresh = new ModuleServiceRegistry();
        Services::injectMock('mcmsModuleServices', $fresh);
        (new ModuleManager($this->db, new AdminRegistry(), $fresh, ROOTPATH . 'modules'))->bootEnabled();
        $html = service('mcmsThemes')->render('page', ['page' => $page, 'siteName' => 'My site', 'metaTitle' => 'Fallback']);
        self::assertStringContainsString('<title>Fallback</title>', $html);
        self::assertSame($page, $pages->find($page['id']));
    }

    public function testPreviewSanitizesCurrentInputWithoutSavingOrPublishing(): void
    {
        [, $services] = $this->boot(['pages', 'news']);
        foreach (['pages.repository', 'news.repository'] as $service) {
            $repository = $services->get($service);
            $data = ['title' => 'Saved draft', 'status' => 'draft', 'body' => '<p>Saved</p>', 'tags' => 'one, two'];
            $stored = $repository->create($data);
            $count = $repository->count();
            $preview = $repository->preview([...$data, 'title' => 'Unsaved <title>', 'status' => 'published',
                'body' => '<p>Unsaved</p><script>alert(1)</script>', 'seo_keywords' => 'custom'], $stored['id']);
            self::assertSame('Unsaved <title>', $preview['title']);
            self::assertSame('<p>Unsaved</p>', $preview['body']);
            self::assertSame('custom', $preview['seo_keywords']);
            self::assertSame($stored, $repository->find($stored['id']));
            self::assertSame($count, $repository->count());
            self::assertNull($repository->findPublishedBySlug($stored['slug']));
            $new = $repository->preview($data);
            self::assertSame(0, $new['id']);
            self::assertSame($count, $repository->count());
            try {
                $repository->preview($data, 999999);
                self::fail('Missing content cannot be previewed as existing.');
            } catch (RuntimeException) {
                self::assertSame($count, $repository->count());
            }
        }
    }

    public function testDraftIsolationRevisionHistoryAndTransactionalRollback(): void
    {
        [, $services] = $this->boot(['pages', 'news']);
        foreach (['pages', 'news'] as $prefix) {
            $repository = $services->get($prefix . '.repository');
            $workflow = new \MCMS\Core\Content\ContentWorkflow($this->db, $prefix);
            $original = $repository->create(['title' => 'Original', 'body' => '<p>Public</p>', 'status' => 'published'], 11);
            $id = $original['id'];
            $workflow->saveDraft(11, $id, ['title' => '', 'body' => '<p>Incomplete</p><script>bad()</script>']);
            $workflow->saveDraft(12, $id, ['title' => 'Other editor', 'body' => '<p>Other</p>']);
            $workflow->saveDraft(11, $id, ['title' => '', 'body' => '<p>Latest</p><img src="/ok" onerror="bad()">']);
            self::assertSame('', $workflow->draft(11, $id)['data']['title']);
            self::assertStringNotContainsString('onerror', $workflow->draft(11, $id)['data']['body']);
            self::assertSame('Other editor', $workflow->draft(12, $id)['data']['title']);
            self::assertNull($workflow->draft(13, $id));
            self::assertSame($original, $repository->find($id));
            self::assertSame(1, $workflow->history($id)['count']);
            $updated = $repository->update($id, ['title' => 'Changed', 'body' => '<p>Changed</p>', 'status' => 'draft'], 12);
            $history = $workflow->history($id);
            self::assertSame(2, $history['count']);
            self::assertSame(12, (int) $history['items'][0]['created_by']);
            $version = $workflow->revision($id, (int) $history['items'][1]['id']);
            self::assertSame('Original', $version['title']);
            self::assertNull($workflow->revision($id + 1, (int) $history['items'][1]['id']));
            self::assertSame($updated, $repository->find($id));
            $workflow->discardDraft(11, $id);
            self::assertNull($workflow->draft(11, $id));
            self::assertNotNull($workflow->draft(12, $id));
            // Simulate an imported record without revisions: capture it before its first edit.
            $this->db->table($prefix . '_revisions')->where('content_id', $id)->delete();
            $repository->update($id, ['title' => 'Imported edit', 'body' => '<p>Edit</p>', 'status' => 'draft'], 11);
            self::assertSame(2, $workflow->history($id)['count']);
            for ($i = 0; $i < 22; $i++) {
                $workflow->record($id, $version, 11);
            }
            self::assertCount(20, $workflow->history($id)['items']);
            self::assertCount(4, $workflow->history($id, 20)['items']);
            $before = $repository->find($id);
            try {
                $workflow->transaction(function () use ($repository, $id, $workflow): void {
                    $repository->update($id, ['title' => 'Rollback', 'body' => '', 'status' => 'draft']);
                    $workflow->record($id, ['invalidJson' => NAN], 11);
                });
                self::fail('Failure recording history must roll back content and its version.');
            } catch (JsonException) {
                self::assertSame($before, $repository->find($id));
                self::assertSame(24, $workflow->history($id)['count']);
            }
            $repository->delete($id);
            self::assertSame(0, $workflow->history($id)['count']);
            self::assertNull($workflow->draft(12, $id));
        }
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
