<?php

declare(strict_types=1);

namespace MCMS\Core\Packages;

use RuntimeException;

/** Private, atomic receipts shared by package lifecycle operations. */
final class PackageState
{
    public static function read(string $path): ?array
    {
        return is_file($path) ? json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR) : null;
    }

    public static function write(string $path, array $data): void
    {
        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0700, true) && ! is_dir(dirname($path))) {
            throw new RuntimeException('Cannot create package receipt directory.');
        }
        $temp = tempnam(dirname($path), 'receipt-');
        if ($temp === false || file_put_contents($temp, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)) === false
            || ! chmod($temp, 0600) || ! rename($temp, $path)) {
            throw new RuntimeException('Cannot persist package receipt.');
        }
    }

    public static function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            if (! unlink($path)) {
                throw new RuntimeException('Cannot remove package file: ' . $path);
            }
        } elseif (is_dir($path)) {
            foreach (new \FilesystemIterator($path) as $file) {
                self::remove($file->getPathname());
            }
            if (! rmdir($path)) {
                throw new RuntimeException('Cannot remove package directory: ' . $path);
            }
        }
    }

    public static function id(string $id): void
    {
        if (preg_match('/^[a-z][a-z0-9_-]*$/D', $id) !== 1) {
            throw new RuntimeException('Invalid package id.');
        }
    }
}
