<?php

declare(strict_types=1);

use MCMS\Core\Storage\ScopedStorage;
use PHPUnit\Framework\TestCase;

final class ScopedStorageTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/mcms-scoped-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($iterator as $entry) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($this->root);
        }
        parent::tearDown();
    }

    public function testModuleNamespacesShareVolumeButNotFilePaths(): void
    {
        $media = new ScopedStorage($this->root, 'media');
        $images = new ScopedStorage($this->root, 'product-images');

        $media->put('2026/10/photo.png', 'media');
        $images->put('2026/10/photo.png', 'image');
        self::assertSame('media', $media->get('2026/10/photo.png'));
        self::assertSame('image', $images->get('2026/10/photo.png'));
        self::assertSame($this->root . '/media', $media->directory());
        self::assertSame($this->root . '/product-images/sets', $images->directory('sets'));
    }

    public function testRejectsCrossNamespaceTraversal(): void
    {
        $storage = new ScopedStorage($this->root, 'media');
        $this->expectException(InvalidArgumentException::class);
        $storage->path('../product-images/secrets');
    }

    public function testRejectsUnsafeOwnerId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ScopedStorage($this->root, '../media');
    }
}
