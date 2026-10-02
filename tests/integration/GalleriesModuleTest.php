<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Core\Navigation\NavigableResourceRegistry;

final class GalleriesModuleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\\Settings'];

    protected function tearDown(): void
    {
        foreach (['Galleries', 'Media'] as $module) {
            $this->migrations->setNamespace('MCMS\\Modules\\' . $module)->regress(0, 'tests');
        }
        parent::tearDown();
    }

    public function testAlbumPublishingOrderingNavigationAndDisablePreserveData(): void
    {
        $services = new ModuleServiceRegistry();
        $navigation = new NavigableResourceRegistry();
        $manager = new ModuleManager($this->db, new AdminRegistry(), $services, ROOTPATH . 'modules', $navigation);
        Services::injectMock('mcmsModules', $manager);
        self::assertFalse($this->db->tableExists('galleries'));
        $manager->enable('galleries');
        $manager->bootEnabled();
        self::assertSame(1, (int) $manager->states()['media']['enabled']);
        $first = $this->media('first.png');
        $second = $this->media('second.png');
        $repository = $services->get('galleries.repository');
        $data = ['title' => 'Первый альбом', 'description' => '<script>text</script>', 'status' => 'draft',
            'media_ids' => [$first, $second, $first], 'positions' => [$first => 10, $second => 0],
            'captions' => [$first => 'First caption', $second => 'Second caption'], 'cover_media_id' => $first];
        $album = $repository->create($data);
        self::assertSame('pervyy-albom', $album['slug']);
        self::assertSame([$second, $first], array_column($album['images'], 'media_id'));
        self::assertSame($first, $album['cover']['id']);
        self::assertNull($repository->findPublishedBySlug($album['slug']));
        self::assertNull($navigation->resolve('galleries.album', (string) $album['id']));
        $album = $repository->update($album['id'], [...$data, 'slug' => $album['slug'], 'status' => 'published']);
        self::assertSame('/galleries/pervyy-albom', $navigation->resolve('galleries.album', (string) $album['id'])['url']);
        self::assertSame('/galleries', $navigation->resolve('galleries.archive', 'index')['url']);
        $html = service('mcmsThemes')->render('galleries.album', ['album' => $album, 'metaTitle' => $album['title']]);
        self::assertStringContainsString('First caption', $html);
        self::assertStringContainsString('&lt;script&gt;text&lt;/script&gt;', $html);
        $manager->disable('galleries');
        self::assertSame(2, $this->db->table('gallery_images')->countAllResults());
        self::assertCount(1, $services->get('media.usage')->find($first));
        $services->get('media.library')->delete($first, true);
        $album = $repository->find($album['id']);
        self::assertNull($album['images'][1]['media']);
        self::assertSame($second, $album['cover']['id']);
        $manager->enable('galleries');
        self::assertSame(1, $repository->countPublished());
        $repository->delete($album['id']);
        self::assertSame(0, $this->db->table('gallery_images')->countAllResults());
        self::assertNotNull($services->get('media.library')->find($second));
    }

    public function testRejectsNonImageWithoutChangingExistingAlbum(): void
    {
        $services = new ModuleServiceRegistry();
        $manager = new ModuleManager($this->db, new AdminRegistry(), $services, ROOTPATH . 'modules');
        $manager->enable('galleries');
        $manager->bootEnabled();
        $repository = $services->get('galleries.repository');
        $album = $repository->create(['title' => 'Album', 'status' => 'draft']);
        $pdf = $this->media('document.pdf', 'application/pdf');
        try {
            $repository->update($album['id'], ['title' => 'Changed', 'status' => 'published', 'media_ids' => [$pdf]]);
            self::fail('PDF must not be accepted as a gallery image.');
        } catch (InvalidArgumentException) {
            self::assertSame('Album', $repository->find($album['id'])['title']);
        }
        self::assertSame(0, $services->get('media.library')->count('', true));
        self::assertSame(1, $services->get('media.library')->count());
        $this->expectException(RuntimeException::class);
        $manager->disable('media');
    }

    private function media(string $filename, string $mime = 'image/png'): int
    {
        $this->db->table('media_files')->insert([
            'original_name' => $filename, 'storage_path' => 'galleries-test/' . $filename, 'mime_type' => $mime,
            'extension' => pathinfo($filename, PATHINFO_EXTENSION), 'size_bytes' => 100,
            'width' => $mime === 'image/png' ? 100 : null, 'height' => $mime === 'image/png' ? 100 : null,
            'sha256' => str_repeat('a', 64), 'created_at' => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insertID();
    }
}
