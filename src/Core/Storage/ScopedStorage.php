<?php

declare(strict_types=1);

namespace MCMS\Core\Storage;

use InvalidArgumentException;
use RuntimeException;

/**
 * A persistent namespace for one module or theme, without a per-extension volume.
 *
 * File APIs use relative names. directory()/path() support existing image
 * processors and uploaded files that require an actual filesystem path.
 * This prevents accidental cross-module writes; trusted PHP is not sandboxed.
 */
final class ScopedStorage implements StorageInterface
{
    private LocalStorage $storage;
    private string $root;

    public function __construct(string $dataRoot, string $ownerId)
    {
        if (preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $ownerId) !== 1) {
            throw new InvalidArgumentException('Unsafe storage owner id.');
        }

        $this->root = rtrim($dataRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $ownerId;
        $this->storage = new LocalStorage($this->root);
    }

    public function put(string $path, string $contents): void
    {
        $this->storage->put($path, $contents);
    }

    public function get(string $path): string
    {
        return $this->storage->get($path);
    }

    public function exists(string $path): bool
    {
        return $this->storage->exists($path);
    }

    public function delete(string $path): void
    {
        $this->storage->delete($path);
    }

    public function path(string $path): string
    {
        return $this->storage->path($path);
    }

    /**
     * Create a directory. Public media roots must be traversable by nginx,
     * whose UID differs from the PHP worker. All other roots remain private.
     */
    public function directory(string $path = '', bool $public = false): string
    {
        $dir = $path === '' ? $this->root : $this->path($path);
        $mode = $public ? 0755 : 0770;
        if (! is_dir($dir) && ! mkdir($dir, $mode, true) && ! is_dir($dir)) {
            throw new RuntimeException('Unable to create scoped storage directory.');
        }
        if ($public && ! chmod($dir, 0755)) {
            throw new RuntimeException('Unable to allow read access to public media directory.');
        }

        return $dir;
    }
}
