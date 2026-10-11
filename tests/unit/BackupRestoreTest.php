<?php

declare(strict_types=1);

use MCMS\Core\Backup\BackupArchive;
use MCMS\Core\Backup\BackupManager;
use MCMS\Core\Backup\RestoreManager;
use PHPUnit\Framework\TestCase;

final class BackupRestoreTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/mcms-restore-test-' . bin2hex(random_bytes(5));
        mkdir($this->root . '/module-data/data/media', 0770, true);
        mkdir($this->root . '/theme-data/installed/sample', 0770, true);
        file_put_contents($this->root . '/module-data/data/media/logo.png', 'Media file');
        file_put_contents($this->root . '/theme-data/installed/sample/theme.json', '{}');
    }

    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            $entry->isDir() && ! $entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->root);
    }

    private function services(): array
    {
        $roots = [
            'modules_data' => $this->root . '/module-data',
            'themes_data' => $this->root . '/theme-data',
        ];
        $manager = new BackupManager($this->root . '/backup-store', $roots, static function (string $file): void {
            file_put_contents($file, "CREATE TABLE smoke_test (id INT);\n");
        });
        return [$manager, new RestoreManager($manager, $this->root . '/backup-store', $roots)];
    }

    public function testStoredAndUploadedArchivesAreInspectedAndStaged(): void
    {
        [$backups, $restore] = $this->services();
        $backup = $backups->create();
        $inspection = BackupArchive::inspect($backups->file($backup));
        self::assertCount(3, $inspection['files']);
        self::assertSame('Media file', file_get_contents($this->root . '/module-data/data/media/logo.png'));
        $stored = $restore->prepareExisting($backup, 'user-1');
        self::assertSame('prepared', $stored['status']);
        self::assertSame('user-1', $restore->state($stored['token'])['owner']);
        self::assertSame($inspection['bytes'], $stored['bytes']);
        self::assertNull($restore->active());

        $upload = $restore->prepareUpload($backups->file($backup), 'user-2');
        self::assertSame('Uploaded ZIP', $upload['label']);
        self::assertNotSame($stored['token'], $upload['token']);
    }

    public function testCorruptedAndUnexpectedArchivesAreRejected(): void
    {
        [, $restore] = $this->services();
        $file = $this->root . '/bad.zip';
        $zip = new ZipArchive();
        self::assertTrue($zip->open($file, ZipArchive::CREATE));
        $zip->addFromString('manifest.json', json_encode([
            'format' => 'mcms-full-backup-v1',
            'created_at' => date(DATE_ATOM),
            'files' => [
                'database.sql' => ['size' => 4, 'sha256' => hash('sha256', 'data')],
                'modules_data/../forbidden' => ['size' => 4, 'sha256' => hash('sha256', 'data')],
            ],
        ]));
        $zip->addFromString('database.sql', 'data');
        $zip->addFromString('modules_data/../forbidden', 'data');
        $zip->close();
        $this->expectException(RuntimeException::class);
        $restore->prepareUpload($file, 'user-1');
    }

    public function testArchiveCannotClaimIncorrectChecksums(): void
    {
        [, $restore] = $this->services();
        $file = $this->root . '/bad-hash.zip';
        $zip = new ZipArchive();
        self::assertTrue($zip->open($file, ZipArchive::CREATE));
        $zip->addFromString('manifest.json', json_encode([
            'format' => 'mcms-full-backup-v1',
            'created_at' => date(DATE_ATOM),
            'files' => ['database.sql' => ['size' => 4, 'sha256' => str_repeat('a', 64)]],
        ]));
        $zip->addFromString('database.sql', 'data');
        $zip->close();
        $this->expectException(RuntimeException::class);
        $restore->prepareUpload($file, 'user-1');
    }

    public function testInvalidStoredBackupsAreNotPrepared(): void
    {
        [, $restore] = $this->services();
        $this->expectException(RuntimeException::class);
        $restore->prepareExisting('../../etc/shadow', 'admin');
    }
}
