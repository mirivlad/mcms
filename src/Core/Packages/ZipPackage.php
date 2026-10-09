<?php

declare(strict_types=1);

namespace MCMS\Core\Packages;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

/** Shared byte/path validation; package-specific policies never execute PHP. */
final class ZipPackage
{
    public const MAX_ARCHIVE_BYTES = 20 * 1024 * 1024;
    private const MAX_CONTENT_BYTES = 50 * 1024 * 1024;
    private const MAX_FILE_BYTES = 8 * 1024 * 1024;
    private const MAX_ENTRIES = 2000;

    public static function pack(string $source, string $output, string $manifestFile, callable $inspect): void
    {
        $source = realpath($source) ?: '';
        if ($source === '' || ! is_file($source . '/' . $manifestFile)) {
            throw new RuntimeException('Select a directory containing ' . $manifestFile . '.');
        }
        if (file_exists($output) || is_link($output)) {
            throw new RuntimeException('Package output already exists.');
        }
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isLink() || ! $file->isFile()) {
                throw new RuntimeException('Packages must contain only regular files.');
            }
            $name = substr($file->getPathname(), strlen($source) + 1);
            self::validatePath($name);
            $files[$name] = $file->getPathname();
        }
        ksort($files);
        $zip = new ZipArchive();
        if ($zip->open($output, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('Cannot create package archive.');
        }
        $open = true;
        try {
            foreach ($files as $name => $path) {
                if (! $zip->addFile($path, $name)) {
                    throw new RuntimeException('Cannot add file to package.');
                }
            }
            if (! $zip->close()) {
                throw new RuntimeException('Cannot write package archive.');
            }
            $open = false;
            $inspect($output);
        } catch (Throwable $e) {
            if ($open) {
                $zip->close();
            }
            @unlink($output);
            throw $e;
        }
    }

    public static function inspectArchive(string $path, string $manifestFile): array
    {
        if (! is_file($path) || filesize($path) > self::MAX_ARCHIVE_BYTES) {
            throw new RuntimeException('Maximum archive size is 20 MiB.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            throw new RuntimeException('Invalid ZIP archive.');
        }
        try {
            if ($zip->numFiles === 0 || $zip->numFiles > self::MAX_ENTRIES) {
                throw new RuntimeException('Package must contain 1 to 2000 entries.');
            }
            $files = [];
            $seen = [];
            $bytes = 0;
            $entries = [];
            $casePaths = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    throw new RuntimeException('Invalid ZIP entry.');
                }
                $name = $stat['name'];
                self::validatePath($name);
                $entries[] = $name;
                $prefix = '';
                foreach (explode('/', rtrim($name, '/')) as $part) {
                    $prefix = $prefix === '' ? $part : $prefix . '/' . $part;
                    $folded = strtolower($prefix);
                    if (isset($casePaths[$folded]) && $casePaths[$folded] !== $prefix) {
                        throw new RuntimeException('Case-conflicting archive directory or file.');
                    }
                    $casePaths[$folded] = $prefix;
                }
                $directory = str_ends_with($name, '/');
                $key = strtolower(rtrim($name, '/'));
                if (isset($seen[$key])) {
                    throw new RuntimeException('Duplicate or case-conflicting archive path.');
                }
                $seen[$key] = $directory;
                $zip->getExternalAttributesIndex($i, $system, $attributes);
                $type = ($attributes >> 16) & 0170000;
                if ($system === ZipArchive::OPSYS_UNIX && $type !== 0
                    && $type !== ($directory ? 0040000 : 0100000)) {
                    throw new RuntimeException('Archive links and special files are forbidden.');
                }
                $bytes += $stat['size'];
                if ($stat['size'] > self::MAX_FILE_BYTES || $bytes > self::MAX_CONTENT_BYTES
                    || ($stat['encryption_method'] ?? 0) !== 0 || ($directory && $stat['size'] !== 0)) {
                    throw new RuntimeException('Package contains encrypted or oversized content.');
                }
                if (! $directory) {
                    $content = $zip->getFromIndex($i);
                    if ($content === false || strlen($content) !== $stat['size']
                        || sprintf('%u', crc32($content)) !== sprintf('%u', $stat['crc'])) {
                        throw new RuntimeException('Package file checksum failed.');
                    }
                    $files[] = $name;
                }
            }
            foreach (array_keys($seen) as $name) {
                $parent = dirname($name);
                while ($parent !== '.') {
                    if (isset($seen[$parent]) && ! $seen[$parent]) {
                        throw new RuntimeException('Archive file conflicts with a directory.');
                    }
                    $parent = dirname($parent);
                }
            }
            if (! in_array($manifestFile, $files, true) || $zip->statName($manifestFile)['size'] > 65536) {
                throw new RuntimeException('Package needs a manifest at its root (maximum 64 KiB).');
            }
            $data = json_decode($zip->getFromName($manifestFile), true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($data)) {
                throw new RuntimeException('Package manifest must be an object.');
            }
            return ['data' => $data, 'files' => $files, 'entries' => $entries, 'bytes' => $bytes, 'sha256' => hash_file('sha256', $path)];
        } finally {
            $zip->close();
        }
    }

    public static function validatePath(string $name): void
    {
        if (strlen($name) > 240 || preg_match('~^(?:[A-Za-z0-9_-][A-Za-z0-9_. -]*/)*[A-Za-z0-9_-][A-Za-z0-9_. -]*/?$~D', $name) !== 1) {
            throw new RuntimeException('Unsafe package path. Use relative paths without dot segments or hidden files.');
        }
        foreach (explode('/', rtrim($name, '/')) as $part) {
            if (str_ends_with($part, '.') || str_ends_with($part, ' ')) {
                throw new RuntimeException('Ambiguous package path.');
            }
        }
    }

    public static function extractArchive(string $archive, string $destination, callable $inspect): array
    {
        $preview = $inspect($archive);
        if (file_exists($destination) || is_link($destination) || ! mkdir($destination, 0755, true)) {
            throw new RuntimeException('Cannot create package extraction directory.');
        }
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Cannot open package archive.');
        }
        try {
            foreach ($preview['files'] as $name) {
                $path = $destination . '/' . $name;
                if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0755, true) && ! is_dir(dirname($path))) {
                    throw new RuntimeException('Cannot create package directory.');
                }
                $content = $zip->getFromName($name);
                if ($content === false || file_put_contents($path, $content) !== strlen($content)) {
                    throw new RuntimeException('Cannot extract package file.');
                }
                chmod($path, 0644);
            }
        } finally {
            $zip->close();
        }
        return $preview;
    }

}
