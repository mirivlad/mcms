<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Modules\Media\Api\MediaLibraryInterface;

final class MediaModuleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = [
        'App',
        'CodeIgniter\Settings',
    ];

    protected function tearDown(): void
    {
        $this->migrations
            ->setNamespace('MCMS\Modules\Media')
            ->regress(0, 'tests');

        parent::tearDown();
    }

    public function testMediaInstallsAndBootsThroughModuleContract(): void
    {
        $admin = new AdminRegistry();
        $services = new ModuleServiceRegistry();
        $manager = new ModuleManager(
            $this->db,
            $admin,
            $services,
            ROOTPATH . 'modules',
        );

        self::assertArrayHasKey('media', $manager->catalog());
        self::assertFalse($this->db->tableExists('media_files'));

        $manager->enable('media');

        self::assertTrue($this->db->tableExists('media_files'));
        self::assertSame(1, (int) $manager->states()['media']['enabled']);
        self::assertSame(20, setting('media.max_upload_mb'));
        self::assertSame(40, setting('media.max_image_megapixels'));
        self::assertContains('image/png', setting('media.allowed_mime_types'));

        $manager->bootEnabled();

        self::assertTrue($services->has('media.library'));
        self::assertInstanceOf(
            MediaLibraryInterface::class,
            $services->get('media.library'),
        );
        self::assertContains('media', array_column($admin->menu(), 'id'));
    }
}
