<?php

declare(strict_types=1);

namespace MCMS\Core\Storage;

use InvalidArgumentException;
use RuntimeException;

final class LocalStorage implements StorageInterface
{
    public function __construct(private readonly string $root)
    {
        if (! is_dir($this->root) && ! mkdir($this->root, 0770, true) && ! is_dir($this->root)) {
            throw new RuntimeException('Unable to create storage root.');
        }
    }

    public function put(string $path, string $contents): void
    {
        $target = $this->absolutePath($path);
        $directory = dirname($target);

        if (! is_dir($directory) && ! mkdir($directory, 0770, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create storage directory.');
        }

        if (file_put_contents($target, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write storage object.');
        }
    }

    public function get(string $path): string
    {
        $target = $this->absolutePath($path);
        $contents = @file_get_contents($target);

        if ($contents === false) {
            throw new RuntimeException("Storage object not found: {$path}");
        }

        return $contents;
    }

    public function exists(string $path): bool
    {
        return is_file($this->absolutePath($path));
    }

    public function delete(string $path): void
    {
        $target = $this->absolutePath($path);

        if (is_file($target) && ! unlink($target)) {
            throw new RuntimeException("Unable to delete storage object: {$path}");
        }
    }

    private function absolutePath(string $path): string
    {
        if (str_contains($path, "\0")) {
            throw new InvalidArgumentException('Storage path contains a NUL byte.');
        }

        $path = str_replace('\\', '/', trim($path, '/'));
        if ($path === '') {
            throw new InvalidArgumentException('Storage path cannot be empty.');
        }

        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidArgumentException('Unsafe storage path.');
            }
        }

        return rtrim($this->root, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . implode(DIRECTORY_SEPARATOR, $segments);
    }
}
