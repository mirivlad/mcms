<?php

declare(strict_types=1);

use MCMS\Core\Backup\BackupManager;
use PHPUnit\Framework\TestCase;

final class BackupManagerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/mcms-backup-test-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/modules-data/media/2026', 0700, true);
        mkdir($this->root . '/themes-data/installed/atelier', 0700, true);
        file_put_contents($this->root . '/modules-data/media/2026/photo.txt', 'photo bytes');
        file_put_contents($this->root . '/themes-data/installed/atelier/theme.json', '{"id":"atelier"}');
    }

    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir() && ! $entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($this->root);
    }

    private function manager(?Closure $dump = null): BackupManager
    {
        return new BackupManager($this->root . '/backups', [
            'modules_data' => $this->root . '/modules-data',
            'themes_data' => $this->root . '/themes-data',
        ], $dump ?? static function (string $path): void {
            file_put_contents($path, 'CREATE TABLE example (id int);');
        });
    }

    public function testCreatesVerifiableArchiveAndListsIt(): void
    {
        $manager = $this->manager();
        $name = $manager->create();
        self::assertMatchesRegularExpression('/^mcms-.*\.zip$/', $name);
        $path = $manager->file($name);
        self::assertNotNull($path);
        self::assertCount(1, $manager->listing());
        self::assertSame($name, $manager->listing()[0]['name']);

        $zip = new ZipArchive();
        self::assertTrue($zip->open($path, ZipArchive::RDONLY));
        try {
            $manifest = json_decode((string) $zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('mcms-full-backup-v1', $manifest['format']);
            self::assertSame('CREATE TABLE example (id int);', $zip->getFromName('database.sql'));
            self::assertSame('photo bytes', $zip->getFromName('modules_data/media/2026/photo.txt'));
            self::assertSame('{"id":"atelier"}', $zip->getFromName('themes_data/installed/atelier/theme.json'));
            foreach ($manifest['files'] as $name => $info) {
                $bytes = $zip->getFromName($name);
                self::assertIsString($bytes);
                self::assertSame(hash('sha256', $bytes), $info['sha256']);
                self::assertSame(strlen($bytes), $info['size']);
            }
            self::assertFalse($zip->getFromName('backups/create.lock'));
        } finally {
            $zip->close();
        }
    }

    public function testNeverPublishesFailedBackup(): void
    {
        $manager = $this->manager(static function (string $path): void {
            throw new RuntimeException('simulated database error');
        });
        try {
            $manager->create();
            self::fail('Must reject failed database export.');
        } catch (RuntimeException $error) {
            self::assertSame('simulated database error', $error->getMessage());
        }
        self::assertSame([], $manager->listing());
        self::assertSame(['create.lock'], array_values(array_diff(scandir($this->root . '/backups'), ['.', '..'])));
    }

    public function testRefusesTraversalAndUnknownFileNames(): void
    {
        $manager = $this->manager();
        self::assertNull($manager->file('../something.zip'));
        self::assertNull($manager->file('manifest.json'));
        self::assertNull($manager->file('mcms-20260101T000000Z-1234567890ab.zip/../../../etc/passwd'));
    }

    public function testRejectsSymlinksInsteadOfBackingUpExternalFiles(): void
    {
        symlink('/etc/hosts', $this->root . '/modules-data/media/leak');
        $manager = $this->manager();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('symbolic links');
        $manager->create();
    }
}
