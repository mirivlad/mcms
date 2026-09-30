<?php

declare(strict_types=1);

use MCMS\Core\Storage\LocalStorage;
use PHPUnit\Framework\TestCase;

final class LocalStorageTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . '/mcms-storage-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $entry) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }

            @rmdir($this->root);
        }

        parent::tearDown();
    }

    public function testStoresReadsAndDeletesObject(): void
    {
        $storage = new LocalStorage($this->root);

        $storage->put('core/example.txt', 'hello');

        self::assertTrue($storage->exists('core/example.txt'));
        self::assertSame('hello', $storage->get('core/example.txt'));

        $storage->delete('core/example.txt');

        self::assertFalse($storage->exists('core/example.txt'));
    }

    public function testRejectsTraversal(): void
    {
        $storage = new LocalStorage($this->root);

        $this->expectException(InvalidArgumentException::class);
        $storage->put('../outside.txt', 'nope');
    }
}
