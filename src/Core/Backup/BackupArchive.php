<?php

declare(strict_types=1);

namespace MCMS\Core\Backup;

use RuntimeException;
use MCMS\Core\Version;
use ZipArchive;

/**
 * Validate the exact mCMS ZIP format before any filesystem or SQL changes.
 * No paths from an archive are ever used as arbitrary extraction targets.
 */
final class BackupArchive
{
    private const MAX_FILES = 50000;
    private const MAX_BYTES = 12 * 1024 * 1024 * 1024; // 12 GiB unpacked

    /** @return array{created_at:string,app_version:string,files:array<string,array{size:int,sha256:string}>,bytes:int} */
    public static function inspect(string $archive): array
    {
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Invalid ZIP archive.');
        }
        try {
            if ($zip->numFiles > self::MAX_FILES || $zip->numFiles < 2) {
                throw new RuntimeException('Unexpected number of backup entries.');
            }
            $manifestText = $zip->getFromName('manifest.json');
            if (! is_string($manifestText) || strlen($manifestText) > 16 * 1024 * 1024) {
                throw new RuntimeException('Missing or oversized backup manifest.');
            }
            $manifest = json_decode($manifestText, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($manifest) || ($manifest['format'] ?? '') !== 'mcms-full-backup-v1'
                || ! is_string($manifest['created_at'] ?? null) || ! is_array($manifest['files'] ?? null)) {
                throw new RuntimeException('Unsupported backup manifest format.');
            }
            $files = $manifest['files'];
            if (! isset($files['app-version.json'])) {
                throw new RuntimeException('Backup has no app-version.json; its mCMS version cannot be verified. Create a new backup.');
            }
            if (count($files) !== $zip->numFiles - 1 || ! isset($files['database.sql'])) {
                throw new RuntimeException('Backup manifest does not match archive.');
            }
            $seen = [];
            $bytes = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (! is_array($stat)) {
                    throw new RuntimeException('Unreadable ZIP entry.');
                }
                $name = $stat['name'];
                if (isset($seen[$name]) || ! self::safePath($name)) {
                    throw new RuntimeException('Duplicate or unsafe ZIP path.');
                }
                $seen[$name] = true;
                if ($name === 'manifest.json') {
                    continue;
                }
                $info = $files[$name] ?? null;
                if (! is_array($info) || ! is_int($info['size'] ?? null)
                    || $info['size'] < 0 || ! is_string($info['sha256'] ?? null)
                    || preg_match('/^[a-f0-9]{64}$/D', $info['sha256']) !== 1
                    || $stat['size'] !== $info['size']) {
                    throw new RuntimeException('Invalid backup file manifest.');
                }
                if ($zip->getExternalAttributesIndex($i, $os, $attr)) {
                    // UNIX symlink, device, FIFO etc must not be extracted.
                    $type = ($attr >> 16) & 0170000;
                    if ($type !== 0 && $type !== 0100000) {
                        throw new RuntimeException('Special files are forbidden in backups.');
                    }
                }
                $bytes += $info['size'];
                if ($bytes > self::MAX_BYTES) {
                    throw new RuntimeException('Backup unpacked size exceeds limit.');
                }
                $stream = $zip->getStream($name);
                if ($stream === false) {
                    throw new RuntimeException('Cannot stream backup file.');
                }
                $hash = hash_init('sha256');
                $read = 0;
                try {
                    while (! feof($stream)) {
                        $data = fread($stream, 1024 * 1024);
                        if ($data === false) {
                            throw new RuntimeException('Unreadable backup data.');
                        }
                        $read += strlen($data);
                        if ($read > $info['size']) {
                            throw new RuntimeException('Archive file size mismatch.');
                        }
                        hash_update($hash, $data);
                    }
                } finally {
                    fclose($stream);
                }
                if ($read !== $info['size'] || ! hash_equals($info['sha256'], hash_final($hash))) {
                    throw new RuntimeException('Backup checksum mismatch: ' . $name);
                }
            }
            $descriptor = $zip->getFromName('app-version.json');
            if (! is_string($descriptor) || strlen($descriptor) > 4096) {
                throw new RuntimeException('Invalid application version file in backup.');
            }
            $metadata = json_decode($descriptor, true);
            if (! is_array($metadata) || ($metadata['application'] ?? null) !== 'mCMS'
                || ! is_string($metadata['version'] ?? null)
                || preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][a-zA-Z0-9.-]+)?$/D', $metadata['version']) !== 1) {
                throw new RuntimeException('Invalid mCMS version metadata in backup.');
            }
            return [
                'created_at' => $manifest['created_at'],
                'app_version' => $metadata['version'],
                'files' => $files,
                'bytes' => $bytes,
            ];
        } finally {
            $zip->close();
        }
    }

    public static function isCompatible(string $archiveVersion): bool
    {
        return $archiveVersion === Version::VERSION;
    }

    public static function assertCompatible(string $archiveVersion): void
    {
        if (! self::isCompatible($archiveVersion)) {
            throw new RuntimeException(sprintf(
                'Backup requires mCMS %s; currently installed mCMS is %s. Install version %s before restoring.',
                $archiveVersion,
                Version::VERSION,
                $archiveVersion,
            ));
        }
    }

    public static function extract(string $archive, array $destinations, string $sqlFile): void
    {
        // Archive must have been validated at this point; stream each file
        // with a fixed destination chosen from two allowlisted data roots.
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Cannot reopen verified ZIP.');
        }
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (! is_string($name) || ! self::safePath($name)) {
                    throw new RuntimeException('Unsafe path during archive extraction.');
                }
                if (in_array($name, ['manifest.json', 'app-version.json'], true)) {
                    continue;
                }
                if ($name === 'database.sql') {
                    $destination = $sqlFile;
                } else {
                    [$prefix, $rest] = explode('/', $name, 2);
                    if (! isset($destinations[$prefix])) {
                        throw new RuntimeException('Unexpected backup data root.');
                    }
                    $destination = $destinations[$prefix] . '/' . $rest;
                }
                $directory = dirname($destination);
                if (! is_dir($directory) && ! mkdir($directory, 0770, true) && ! is_dir($directory)) {
                    throw new RuntimeException('Cannot create restore staging directory.');
                }
                $stream = $zip->getStream($name);
                $out = fopen($destination, 'xb');
                if ($stream === false || $out === false) {
                    throw new RuntimeException('Cannot extract backup member.');
                }
                try {
                    if (stream_copy_to_stream($stream, $out) === false) {
                        throw new RuntimeException('Error extracting ZIP data.');
                    }
                } finally {
                    fclose($stream);
                    fclose($out);
                }
                if (str_starts_with($name, 'modules_data/data/media/')) {
                    chmod($destination, 0644);
                    $cursor = $directory;
                    while ($cursor !== $destinations['modules_data'] && str_starts_with($cursor, $destinations['modules_data'] . '/')) {
                        chmod($cursor, 0755);
                        $cursor = dirname($cursor);
                    }
                }
            }
        } finally {
            $zip->close();
        }
    }

    private static function safePath(string $path): bool
    {
        if ($path === '' || str_contains($path, '\\') || str_contains($path, "\0")
            || str_starts_with($path, '/') || str_contains($path, '//')
            || str_ends_with($path, '/')) {
            return false;
        }
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                return false;
            }
        }
        return $path === 'manifest.json' || $path === 'database.sql'
            || $path === 'app-version.json'
            || str_starts_with($path, 'modules_data/')
            || str_starts_with($path, 'themes_data/');
    }
}
