<?php

declare(strict_types=1);

namespace MCMS\Core\Backup;

use Closure;
use FilesystemIterator;
use InvalidArgumentException;
use MCMS\Core\Version;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Creates and lists self-contained site backups in an independent directory.
 * The database is exported transactionally; filesystem files must be stable
 * while a backup is taken (see docs/BACKUPS.md).
 */
final class BackupManager
{
    private const NAME = '/^mcms-\d{8}T\d{6}Z-[a-f0-9]{12}\.zip$/D';

    /** @param array<string, string> $roots ZIP prefix => absolute source directory */
    public function __construct(
        private readonly string $directory,
        private readonly array $roots,
        private readonly ?Closure $dump = null,
    ) {
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Cannot create backup directory.');
        }
        if (! is_writable($directory)) {
            throw new RuntimeException('Backup directory is not writable.');
        }
    }

    /** @return list<array{name:string,size:int,created_at:int}> */
    public function listing(): array
    {
        $files = [];
        foreach (glob($this->directory . '/mcms-*.zip') ?: [] as $path) {
            $name = basename($path);
            if (! self::validName($name) || ! is_file($path) || is_link($path)) {
                continue;
            }
            $files[] = [
                'name' => $name,
                'size' => (int) filesize($path),
                'created_at' => (int) filemtime($path),
            ];
        }
        usort($files, static fn (array $a, array $b): int => strcmp($b['name'], $a['name']));
        return $files;
    }

    public function file(string $name): ?string
    {
        if (! self::validName($name)) {
            return null;
        }
        $path = $this->directory . DIRECTORY_SEPARATOR . $name;
        return is_file($path) && ! is_link($path) ? $path : null;
    }

    /**
     * Delete only published backup ZIPs. No archive may be removed while a
     * backup or full-site restoration is running.
     *
     * @return bool false if no file with this (safe) name exists
     */
    public function delete(string $name): bool
    {
        if (! self::validName($name)) {
            return false;
        }

        $lock = fopen($this->directory . '/create.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Cannot delete a backup while another backup is running.');
        }

        try {
            if (is_file($this->directory . '/restore-active.json')) {
                throw new RuntimeException('Cannot delete backups during site restoration.');
            }

            $path = $this->file($name);
            if ($path === null) {
                return false;
            }

            if (! unlink($path)) {
                throw new RuntimeException('Unable to delete backup archive.');
            }

            return true;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function create(): string
    {
        $lock = fopen($this->directory . '/create.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('A backup is already running.');
        }
        try {
            return $this->createLocked();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function createLocked(): string
    {
        $name = 'mcms-' . gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(6)) . '.zip';
        $dest = $this->directory . '/' . $name;
        $partial = $this->directory . '/.' . $name . '.part';
        $dumpPath = $this->directory . '/.' . $name . '.sql';
        $zip = new ZipArchive();
        $opened = false;
        try {
            $this->dumpDatabase($dumpPath);
            if ($zip->open($partial, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new RuntimeException('Cannot open backup archive.');
            }
            $opened = true;

            $files = [];
            // A signed-off backup is always tied to an exact mCMS Core version.
            // Keep the descriptor as an actual ZIP member, covered by the
            // manifest's SHA-256 just like database.sql and extension files.
            $versionJson = json_encode([
                'application' => 'mCMS',
                'version' => Version::VERSION,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            if (! $zip->addFromString('app-version.json', $versionJson)) {
                throw new RuntimeException('Cannot add application version to the archive.');
            }
            $files['app-version.json'] = [
                'size' => strlen($versionJson),
                'sha256' => hash('sha256', $versionJson),
            ];
            $this->addFile($zip, $dumpPath, 'database.sql', $files);
            foreach ($this->roots as $prefix => $root) {
                if (! in_array($prefix, ['modules_data', 'themes_data'], true)) {
                    throw new InvalidArgumentException('Unsupported backup root.');
                }
                if (! is_dir($root)) {
                    throw new RuntimeException("Missing required storage directory: {$prefix}");
                }
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::LEAVES_ONLY,
                );
                foreach ($iterator as $entry) {
                    if ($entry->isLink()) {
                        throw new RuntimeException('Backup refuses symbolic links: ' . $entry->getPathname());
                    }
                    if (! $entry->isFile()) {
                        continue;
                    }
                    $relative = substr($entry->getPathname(), strlen(rtrim($root, '/')) + 1);
                    $this->addFile($zip, $entry->getPathname(), $prefix . '/' . str_replace('\\', '/', $relative), $files);
                }
            }
            ksort($files);
            $manifest = json_encode([
                'format' => 'mcms-full-backup-v1',
                'created_at' => gmdate(DATE_ATOM),
                'files' => $files,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (! $zip->addFromString('manifest.json', $manifest . "\n")) {
                throw new RuntimeException('Cannot write backup manifest.');
            }
            $opened = false;
            if (! $zip->close()) {
                throw new RuntimeException('Cannot finish backup archive.');
            }
            if (! $this->verifyArchive($partial, $files)) {
                throw new RuntimeException('Backup failed ZIP integrity verification (files changed during creation?).');
            }
            if (! chmod($partial, 0600) || ! rename($partial, $dest)) {
                throw new RuntimeException('Cannot publish completed backup.');
            }
            return $name;
        } catch (Throwable $e) {
            if ($opened) {
                $zip->close();
            }
            @unlink($partial);
            throw $e;
        } finally {
            @unlink($dumpPath);
        }
    }

    /** @param array<string, array{size:int,sha256:string}> $files */
    private function addFile(ZipArchive $zip, string $path, string $name, array &$files): void
    {
        if (! is_file($path) || is_link($path)) {
            throw new RuntimeException('Invalid backup file: ' . $name);
        }
        $hash = hash_file('sha256', $path);
        if ($hash === false || ! $zip->addFile($path, $name)) {
            throw new RuntimeException('Cannot add backup file: ' . $name);
        }
        $files[$name] = ['size' => (int) filesize($path), 'sha256' => $hash];
    }

    /** @param array<string, array{size:int,sha256:string}> $expected */
    private function verifyArchive(string $path, array $expected): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return false;
        }
        try {
            if ($zip->numFiles !== count($expected) + 1) {
                return false;
            }
            foreach ($expected as $name => $info) {
                $stream = $zip->getStream($name);
                if ($stream === false) {
                    return false;
                }
                $hash = hash_init('sha256');
                $size = 0;
                while (! feof($stream)) {
                    $chunk = fread($stream, 1024 * 1024);
                    if ($chunk === false) {
                        fclose($stream);
                        return false;
                    }
                    $size += strlen($chunk);
                    hash_update($hash, $chunk);
                }
                fclose($stream);
                if ($size !== $info['size'] || hash_final($hash) !== $info['sha256']) {
                    return false;
                }
            }
            $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
            return is_array($manifest)
                && ($manifest['format'] ?? '') === 'mcms-full-backup-v1'
                && ($manifest['files'] ?? null) === $expected;
        } finally {
            $zip->close();
        }
    }

    private function dumpDatabase(string $destination): void
    {
        if ($this->dump !== null) {
            ($this->dump)($destination);
            if (! is_file($destination)) {
                throw new RuntimeException('Backup database dumper produced no file.');
            }
            return;
        }
        $host = getenv('MCMS_DB_HOST') ?: 'db';
        $port = (string) (getenv('MCMS_DB_PORT') ?: '3306');
        $db = getenv('MCMS_DB_NAME') ?: 'mcms';
        $user = getenv('MCMS_DB_USER') ?: 'mcms';
        $pass = \MCMS\Core\Install\SharedHostSetup::databasePassword();
        if (! is_string($pass) || $pass === '') {
            throw new RuntimeException('Database password is not configured.');
        }
        // An argv array prevents shell injection; the password is never an argv.
        $cmd = [
            'mariadb-dump', '--single-transaction', '--quick', '--skip-lock-tables',
            '--hex-blob', '--default-character-set=utf8mb4',
            '--routines', '--triggers', '--host=' . $host, '--port=' . $port,
            '--user=' . $user, '--', $db,
        ];
        $stderr = $destination . '.err';
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $destination, 'w'],
            2 => ['file', $stderr, 'w'],
        ];
        $env = getenv();
        $env = is_array($env) ? $env : [];
        $env['MYSQL_PWD'] = $pass;
        $process = proc_open($cmd, $descriptors, $pipes, null, $env);
        if (! is_resource($process)) {
            throw new RuntimeException('Cannot launch mariadb-dump.');
        }
        fclose($pipes[0]);
        $status = proc_close($process);
        $error = is_file($stderr) ? trim((string) file_get_contents($stderr)) : '';
        @unlink($stderr);
        if ($status !== 0 || ! is_file($destination) || filesize($destination) === 0) {
            throw new RuntimeException('Database backup failed: ' . substr($error, 0, 300));
        }
    }

    private static function validName(string $name): bool
    {
        return preg_match(self::NAME, $name) === 1;
    }
}
