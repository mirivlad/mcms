<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Core\Navigation\NavigableResourceRegistry;

final class ThemeHomeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\\Settings'];

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        foreach (['Feedback', 'Galleries', 'News', 'Pages', 'Media'] as $module) {
            $this->migrations->setNamespace('MCMS\\Modules\\' . $module)->regress(0, 'tests');
        }
        service('language')->setLocale('en');
        parent::tearDown();
    }

    public function testHomeSettingsRemainIndependentAndRenderEscapedText(): void
    {
        $themes = service('mcmsThemes');
        foreach (['mcms-depth', 'mcms-focus'] as $id) {
            $themes->activate($id);
            $values = $themes->homeContent();
            $values['title'] = $id . " <script>alert(1)</script>\nSecond line";
            $values['description'] = '<img src=x onerror=bad()>';
            $values['button_url'] = '/news?tag=one&category=2';
            $themes->saveHomeContent($id, $values);
            $html = $themes->render('home');
            self::assertStringContainsString('data-theme="' . $id . '"', $html);
            self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;<br', $html);
            self::assertStringContainsString('&lt;img src=x onerror=bad()&gt;', $html);
            self::assertStringContainsString('news?tag=one&category=2', html_entity_decode($html));
            self::assertStringNotContainsString('<script>alert(1)</script>', $html);
            self::assertStringNotContainsString('fonts.googleapis.com', $html);
        }
        $themes->activate('mcms-depth');
        self::assertStringStartsWith('mcms-depth ', $themes->homeContent()['title']);
        $themes->activate('mcms-focus');
        self::assertStringStartsWith('mcms-focus ', $themes->homeContent()['title']);
    }

    public function testInvalidHomeInputDoesNotReplaceSavedSettings(): void
    {
        $themes = service('mcmsThemes');
        $themes->activate('mcms-depth');
        $values = $themes->homeContent();
        $themes->saveHomeContent('mcms-depth', $values);
        $before = $themes->homeContent();
        foreach ([['title' => ''], ['title' => str_repeat('x', 181)], ['title' => ['array']],
            ['button_url' => 'javascript:alert(1)'], ['button_url' => '//evil.test'],
            ['button_url' => '/\\evil.test'], ['button_url' => "https://example.com/\n" . 'bad'],
            ['image_media_id' => 99999]] as $invalid) {
            try {
                $themes->saveHomeContent('mcms-depth', array_replace($values, $invalid));
                self::fail('Invalid Home input must be rejected.');
            } catch (InvalidArgumentException) {
                self::assertSame($before, $themes->homeContent());
            }
        }
        $this->expectException(InvalidArgumentException::class);
        $themes->saveHomeContent('mcms-default', $values);
    }

    public function testPublicContentAndSectionTogglesRespectPublication(): void
    {
        [, $services] = $this->boot(['pages', 'news', 'galleries', 'feedback']);
        $pages = $services->get('pages.repository');
        $page = $pages->create(['title' => 'Reader page', 'body' => '<p>Reader content</p>', 'status' => 'published']);
        $news = $services->get('news.repository');
        $article = $news->create(['title' => 'Published story', 'body' => '<p>Story content</p>', 'status' => 'published']);
        $news->create(['title' => 'Private story', 'body' => '', 'status' => 'draft']);
        $news->create(['title' => 'Future story', 'body' => '', 'status' => 'published', 'published_at' => date('Y-m-d H:i:s', time() + 3600)]);
        $galleries = $services->get('galleries.repository');
        $album = $galleries->create(['title' => 'Published album', 'description' => 'Album text', 'status' => 'published']);
        $galleries->create(['title' => 'Private album', 'description' => '', 'status' => 'draft']);
        $themes = service('mcmsThemes');
        foreach (['mcms-depth', 'mcms-focus'] as $id) {
            $themes->activate($id);
            $home = $themes->render('home');
            self::assertStringContainsString('Published story', $home);
            self::assertStringContainsString('Published album', $home);
            self::assertStringNotContainsString('Private story', $home);
            self::assertStringNotContainsString('Future story', $home);
            self::assertStringNotContainsString('Private album', $home);
            $values = $themes->homeContent();
            $themes->saveHomeContent($id, [...$values, 'show_news' => false, 'show_galleries' => false]);
            $hidden = $themes->render('home');
            self::assertStringNotContainsString('Published story', $hidden);
            self::assertStringNotContainsString('Published album', $hidden);
            self::assertStringContainsString(esc($values['title']), str_replace('<br />', '', $hidden));
            foreach ([
                ['page', ['page' => $page]],
                ['news.index', ['articles' => [$article], 'page' => 1, 'lastPage' => 1]],
                ['news.article', ['article' => $article]],
                ['galleries.index', ['albums' => [$album], 'page' => 1, 'lastPage' => 1]],
                ['galleries.album', ['album' => $album]],
                ['feedback.form', ['values' => [], 'error' => null, 'sent' => false]],
            ] as [$template, $data]) {
                $html = $themes->render($template, $data);
                self::assertStringContainsString('data-theme="' . $id . '"', $html);
                self::assertStringNotContainsString('site-home"', $html);
            }
            self::assertStringContainsString('site-gallery-embed', $themes->render('galleries.embed', ['album' => $album]));
            service('settings')->set('pages.home_page_id', $page['id']);
            self::assertStringContainsString('Reader content', $services->get('pages.home')->renderHome());
            self::assertSame($page['id'], (int) setting('pages.home_page_id'));
        }
    }

    public function testCoverUsageAndMediaDisablePreserveTheAssignment(): void
    {
        [$modules, $services] = $this->boot(['media']);
        $this->db->table('media_files')->insert([
            'original_name' => 'cover.png', 'storage_path' => 'cover.png', 'mime_type' => 'image/png',
            'extension' => 'png', 'size_bytes' => 100, 'width' => 800, 'height' => 400,
            'sha256' => str_repeat('a', 64), 'title' => 'Home cover', 'alt_text' => '',
            'uploaded_by' => null, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => null,
        ]);
        $imageId = (int) $this->db->insertID();
        $themes = service('mcmsThemes');
        $themes->activate('mcms-depth');
        $values = [...$themes->homeContent(), 'image_media_id' => $imageId];
        $themes->saveHomeContent('mcms-depth', $values);
        $usages = $services->get('media.usage')->find($imageId);
        self::assertCount(1, $usages);
        self::assertStringContainsString('mcms-depth', $usages[0]['edit_url']);
        self::assertSame($imageId, $themes->homeContent()['image']['id']);
        $modules->disable('media');
        $fresh = new ModuleServiceRegistry();
        Services::injectMock('mcmsModuleServices', $fresh);
        (new ModuleManager($this->db, new AdminRegistry(), $fresh, ROOTPATH . 'modules'))->bootEnabled();
        self::assertNull($themes->homeContent()['image']);
        self::assertSame($imageId, $themes->homeContent()['image_media_id']);
        $themes->saveHomeContent('mcms-depth', $values);
        self::assertStringContainsString('images/hero.webp', html_entity_decode($themes->render('home')));
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
