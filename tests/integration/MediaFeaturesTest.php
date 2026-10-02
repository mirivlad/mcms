<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Modules\Media\Services\MediaLibrary;

final class MediaFeaturesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\\Settings'];
    private string $root;

    protected function tearDown(): void
    {
        foreach (['News', 'Pages', 'Media'] as $module) {
            $this->migrations->setNamespace('MCMS\\Modules\\' . $module)->regress(0, 'tests');
        }
        if (isset($this->root)) {
            foreach (glob($this->root . '/*') ?: [] as $path) {
                unlink($path);
            }
            rmdir($this->root);
        }
        parent::tearDown();
    }

    public function testThumbnailAndUsedFileDeletionIncludeDisabledContent(): void
    {
        $services = new ModuleServiceRegistry();
        $manager = new ModuleManager($this->db, new AdminRegistry(), $services, ROOTPATH . 'modules');
        Services::injectMock('mcmsModules', $manager);
        $manager->enable('pages');
        $manager->enable('news');
        $manager->bootEnabled();
        $this->root = sys_get_temp_dir() . '/mcms-images-' . bin2hex(random_bytes(6));
        mkdir($this->root);
        $image = imagecreatetruecolor(960, 480);
        imagepng($image, $this->root . '/fixture.png');
        imagedestroy($image);
        $this->db->table('media_files')->insert([
            'original_name' => 'fixture.png', 'storage_path' => 'fixture.png', 'mime_type' => 'image/png',
            'extension' => 'png', 'size_bytes' => filesize($this->root . '/fixture.png'),
            'width' => 960, 'height' => 480, 'sha256' => hash_file('sha256', $this->root . '/fixture.png'),
            'title' => null, 'alt_text' => null, 'uploaded_by' => null, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => null,
        ]);
        $id = (int) $this->db->insertID();
        $library = new MediaLibrary($this->db, $this->root);
        $thumbnailUrl = $library->thumbnailUrl($id);
        self::assertStringEndsWith('.thumb.png', $thumbnailUrl);
        self::assertSame([480, 240], array_slice(getimagesize($this->root . '/fixture.png.thumb.png'), 0, 2));
        self::assertSame([960, 480], array_slice(getimagesize($this->root . '/fixture.png'), 0, 2));

        $services->get('pages.repository')->create(['title' => 'Page', 'body' => '<img src="/assets/uploads/media/fixture.png">', 'status' => 'draft']);
        $services->get('news.repository')->create(['title' => 'Article', 'body' => '', 'status' => 'draft', 'featured_media_id' => $id]);
        $manager->disable('pages');
        $manager->disable('news');
        self::assertCount(2, $services->get('media.usage')->find($id));
        try {
            $library->delete($id);
            self::fail('Used file must require a separate confirmation.');
        } catch (RuntimeException) {
            self::assertNotNull($library->find($id));
            self::assertFileExists($this->root . '/fixture.png');
        }
        $library->delete($id, true);
        self::assertNull($library->find($id));
        self::assertFileDoesNotExist($this->root . '/fixture.png');
        self::assertFileDoesNotExist($this->root . '/fixture.png.thumb.png');
        self::assertSame(1, $this->db->table('pages')->countAllResults());
        self::assertSame(1, $this->db->table('news_articles')->countAllResults());
    }
}
