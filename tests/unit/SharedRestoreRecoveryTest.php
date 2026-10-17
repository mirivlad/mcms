<?php

declare(strict_types=1);

namespace Tests\Unit;

use MCMS\Core\Backup\BackupManager;
use MCMS\Core\Backup\RestoreManager;
use PHPUnit\Framework\TestCase;

final class SharedRestoreRecoveryTest extends TestCase
{
    public function testFreshQueuedRestoreIsNotCancelledButAbandonedQueueIsReleased(): void
    {
        $previous = getenv('MCMS_INSTALL_MODE');
        $root = sys_get_temp_dir() . '/mcms-restore-test-' . bin2hex(random_bytes(8));
        $backups = $root . '/backups';
        $modules = $root . '/modules';
        $themes = $root . '/themes';
        mkdir($root, 0700);
        mkdir($modules, 0700);
        mkdir($themes, 0700);
        putenv('MCMS_INSTALL_MODE=shared');
        try {
            $manager = new BackupManager($backups, ['modules_data' => $modules, 'themes_data' => $themes]);
            $restore = new RestoreManager($manager, $backups, ['modules_data' => $modules, 'themes_data' => $themes]);
            $token = bin2hex(random_bytes(16));
            $state = [
                'token' => $token, 'status' => 'queued', 'queued_at' => time(),
                'prepared_at' => time(), 'safety' => null,
            ];
            $stateFile = $backups . '/restore/' . $token . '.json';
            $active = $backups . '/restore-active.json';
            file_put_contents($stateFile, json_encode($state));
            file_put_contents($active, json_encode(['token' => $token, 'status' => 'queued']));
            self::assertFalse($restore->recoverInterrupted());
            self::assertFileExists($active);
            $state['queued_at'] = time() - 180;
            file_put_contents($stateFile, json_encode($state));
            self::assertTrue($restore->recoverInterrupted());
            self::assertFileDoesNotExist($active);
            self::assertSame('failed', $restore->state($token)['status']);
        } finally {
            putenv($previous === false ? 'MCMS_INSTALL_MODE' : 'MCMS_INSTALL_MODE=' . $previous);
            foreach (new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            ) as $entry) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($root);
        }
    }
}
