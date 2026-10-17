<?php

declare(strict_types=1);

/**
 * Stable, zero-framework recovery shim. Its path is outside any directory
 * replaced by an upgrade. It must not trust request parameters or load mCMS
 * until an interrupted code swap has been rolled back.
 */
final class MCMSSharedCoreRescue
{
    private const ROOTS = ['app', 'src', 'modules', 'themes', 'catalog', 'vendor', 'public_html/assets'];

    public static function gate(string $root): void
    {
        $root = rtrim($root, '/') . '/';
        $work = $root . 'writable/backups/shared-core-updates/';
        $active = $work . 'active.json';
        if (! is_file($active)) {
            return;
        }
        $state = json_decode((string) file_get_contents($active), true);
        $token = $state['token'] ?? null;
        if (! is_string($token) || preg_match('/^[a-f0-9]{32}$/D', $token) !== 1) {
            self::blocked();
        }
        $status = $state['status'] ?? '';
        $url = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if ($status === 'swapped' && time() - (int) ($state['swapped_at'] ?? 0) < 600) {
            if (in_array($url, [
                '/admin/updates/shared-core/finish', '/admin/updates', '/login',
            ], true)) {
                return; // Core auth + CSRF must still apply.
            }
            self::blocked();
        }
        $lock = fopen($work . 'core.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            self::blocked(); // the original updater still owns the lock.
        }
        try {
            self::rollback($root, $work, $state);
        } catch (Throwable $error) {
            // No exception detail is sent to anonymous traffic.
            @file_put_contents($work . 'recovery-error.log', gmdate(DATE_ATOM) . ' ' . $error->getMessage() . "\n", FILE_APPEND | LOCK_EX);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        self::blocked(); // client retries only after the backup is restored.
    }

    public static function rollback(string $root, string $work, array $state): void
    {
        $token = $state['token'] ?? null;
        if (! is_string($token) || preg_match('/^[a-f0-9]{32}$/D', $token) !== 1) {
            throw new RuntimeException('Unsafe recovery receipt.');
        }
        $root = rtrim($root, '/') . '/';
        $work = rtrim($work, '/') . '/';
        $previousRoot = $work . 'previous-' . $token . '/';
        foreach (self::ROOTS as $relative) {
            $previous = $previousRoot . $relative;
            if (! is_dir($previous)) {
                continue;
            }
            $installed = $root . $relative;
            $failed = $work . 'failed-' . $token . '/' . $relative;
            if (! is_dir(dirname($failed))) {
                mkdir(dirname($failed), 0700, true);
            }
            if (is_dir($installed)) {
                if (file_exists($failed)) {
                    self::erase($failed);
                }
                if (! rename($installed, $failed)) {
                    throw new RuntimeException('Cannot preserve failed Core code.');
                }
            }
            if (! rename($previous, $installed)) {
                throw new RuntimeException('Cannot roll back Core code directory.');
            }
        }
        // A failed migration may have committed some MySQL DDL. Recover both
        // the database and shared module/theme files from the original safety
        // snapshot, not only the PHP source directories.
        if (($state['status'] ?? '') === 'finishing') {
            self::restoreSafety($root, $work, $state);
        }
        $state['status'] = 'rolled_back';
        $state['reason'] = 'Core update interrupted or failed; previous code and database restored.';
        self::write($work . 'last.json', $state);
        if (! unlink($work . 'active.json')) {
            throw new RuntimeException('Cannot close Core update maintenance.');
        }
    }

    private static function restoreSafety(string $root, string $work, array $state): void
    {
        $safety = $state['safety'] ?? '';
        if (! is_string($safety) || preg_match('/^mcms-[0-9]{8}T[0-9]{6}Z-[a-f0-9]{12}\.zip$/D', $safety) !== 1) {
            throw new RuntimeException('Missing original safety backup.');
        }
        $archive = $root . 'writable/backups/' . $safety;
        require_once $root . 'vendor/autoload.php';
        // Some older release Composer autoloaders were generated before the
        // PHP-only transfer driver existed; load recovery classes by their
        // fixed private paths rather than depending on an old classmap.
        if (! class_exists(\MCMS\Core\Install\SharedHostSetup::class)) {
            require_once $root . 'src/Core/Install/SharedHostSetup.php';
        }
        if (! class_exists(\MCMS\Core\Backup\PhpDatabaseTransfer::class)) {
            require_once $root . 'src/Core/Backup/PhpDatabaseTransfer.php';
        }
        (new \CodeIgniter\Config\DotEnv($root))->load();
        $report = \MCMS\Core\Backup\BackupArchive::inspect($archive);
        \MCMS\Core\Backup\BackupArchive::assertCompatible($report['app_version']);
        \MCMS\Core\Backup\PhpDatabaseTransfer::assertArchiveFormat($archive);
        $rescue = $work . 'rescue-' . $state['token'];
        self::erase($rescue);
        mkdir($rescue, 0700, true);
        $stages = ['modules_data' => $rescue . '/modules_data', 'themes_data' => $rescue . '/themes_data'];
        foreach ($stages as $dir) {
            mkdir($dir, 0700, true);
        }
        $sql = $rescue . '/database.sql';
        \MCMS\Core\Backup\BackupArchive::extract($archive, $stages, $sql);
        foreach ($stages as $prefix => $source) {
            $dest = $root . 'writable/' . str_replace('_', '-', $prefix);
            if (! is_dir($dest)) {
                mkdir($dest, 0700, true);
            }
            foreach (scandir($dest) ?: [] as $name) {
                if ($name !== '.' && $name !== '..') {
                    self::erase($dest . '/' . $name);
                }
            }
            foreach (scandir($source) ?: [] as $name) {
                if ($name !== '.' && $name !== '..' && ! rename($source . '/' . $name, $dest . '/' . $name)) {
                    throw new RuntimeException('Cannot recover original module/theme data.');
                }
            }
        }
        \MCMS\Core\Backup\PhpDatabaseTransfer::import($sql);
    }

    private static function erase(string $path): void
    {
        if (! file_exists($path) && ! is_link($path)) {
            return;
        }
        if (! is_dir($path) || is_link($path)) {
            if (! unlink($path)) {
                throw new RuntimeException('Recovery cannot remove file.');
            }
            return;
        }
        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        ) as $entry) {
            $entry->isDir() && ! $entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($path);
    }

    private static function write(string $path, array $value): void
    {
        $temp = $path . '.' . bin2hex(random_bytes(6));
        if (file_put_contents($temp, json_encode($value, JSON_THROW_ON_ERROR)) === false || ! rename($temp, $path)) {
            throw new RuntimeException('Cannot persist recovery state.');
        }
    }

    private static function blocked(): never
    {
        http_response_code(503);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store');
        header('Retry-After: 10');
        echo 'mCMS maintenance: Core update/recovery is in progress. Retry shortly.';
        exit;
    }
}
