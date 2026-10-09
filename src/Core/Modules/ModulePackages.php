<?php

declare(strict_types=1);

namespace MCMS\Core\Modules;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use MCMS\Core\Version;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

/** Packages contain trusted executable PHP, but inspection never loads it. */
final class ModulePackages
{
    public const MAX_ARCHIVE_BYTES = 20 * 1024 * 1024;
    private const MAX_CONTENT_BYTES = 50 * 1024 * 1024;
    private const MAX_FILE_BYTES = 8 * 1024 * 1024;
    private const MAX_ENTRIES = 2000;
    private const PREVIEW_TTL = 3600;

    public function __construct(private readonly ModuleManager $modules, private readonly string $root)
    {
    }

    public static function pack(string $source, string $output): void
    {
        $source = realpath($source) ?: '';
        if ($source === '' || ! is_file($source . '/module.json')) {
            throw new RuntimeException('Select a module directory containing module.json.');
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
            self::inspectArchive($output);
        } catch (Throwable $e) {
            if ($open) {
                $zip->close();
            }
            @unlink($output);
            throw $e;
        }
    }

    public function prepare(string $archive): array
    {
        $this->directory($this->root . '/pending');
        foreach (glob($this->root . '/pending/*.zip') ?: [] as $expired) {
            if (filemtime($expired) < time() - self::PREVIEW_TTL) {
                unlink($expired);
            }
        }
        $token = bin2hex(random_bytes(24));
        $path = $this->pendingPath($token);
        if (! is_file($archive) || filesize($archive) > self::MAX_ARCHIVE_BYTES || ! copy($archive, $path)) {
            throw new RuntimeException('Cannot stage package; maximum archive size is 20 MiB.');
        }
        chmod($path, 0600);
        try {
            $preview = self::inspectArchive($path);
            $this->checkCompatibility($preview['manifest']);
            return [...$preview, 'token' => $token];
        } catch (Throwable $e) {
            unlink($path);
            throw $e;
        }
    }

    public function cancel(string $token): void
    {
        $path = $this->pendingPath($token);
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function install(string $token, string $sha256): ModuleManifest
    {
        $path = $this->pendingPath($token);
        $this->directory($this->root . '/installed');
        $lock = fopen($this->root . '/install.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException('Cannot lock module installation.');
        }
        $stage = $this->root . '/pending/' . $token;
        try {
            if (! is_file($path) || filemtime($path) < time() - self::PREVIEW_TTL
                || ! hash_equals($sha256, (string) hash_file('sha256', $path))) {
                throw new RuntimeException('Package preview expired or the archive changed. Upload it again.');
            }
            $preview = self::inspectArchive($path);
            $manifest = $preview['manifest'];
            $this->checkCompatibility($manifest);
            $folder = substr($manifest->namespace, strlen('MCMS\\Modules\\'));
            $destination = $this->root . '/installed/' . $folder;
            if (file_exists($destination) || is_link($destination)) {
                throw new RuntimeException('Module directory already exists; package updates are not supported here.');
            }
            if (file_exists($stage)) {
                throw new RuntimeException('Package staging directory already exists.');
            }
            $this->directory($stage);
            $zip = new ZipArchive();
            if ($zip->open($path, ZipArchive::RDONLY) !== true) {
                throw new RuntimeException('Cannot open staged archive.');
            }
            try {
                foreach ($preview['files'] as $name) {
                    $target = $stage . '/' . $name;
                    $this->directory(dirname($target));
                    $content = $zip->getFromName($name);
                    if ($content === false || file_put_contents($target, $content) !== strlen($content)) {
                        throw new RuntimeException('Cannot write package file.');
                    }
                    chmod($target, 0644);
                }
            } finally {
                $zip->close();
            }
            if (! rename($stage, $destination)) {
                throw new RuntimeException('Cannot publish module directory.');
            }
            unlink($path);
            return ModuleManifest::fromFile($destination . '/module.json');
        } finally {
            if (is_dir($stage)) {
                foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, \FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                    $file->isDir() && ! $file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
                }
                rmdir($stage);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function inspectArchive(string $path): array
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
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    throw new RuntimeException('Invalid ZIP entry.');
                }
                $name = $stat['name'];
                self::validatePath($name);
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
            if (! in_array('module.json', $files, true) || $zip->statName('module.json')['size'] > 65536) {
                throw new RuntimeException('Package needs module.json at its root (maximum 64 KiB).');
            }
            $data = json_decode($zip->getFromName('module.json'), true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($data)) {
                throw new RuntimeException('Module manifest must be an object.');
            }
            foreach (['requires', 'settings', 'permissions', 'grants', 'public_paths', 'data_providers'] as $field) {
                if (isset($data[$field]) && ! is_array($data[$field])) {
                    throw new RuntimeException('Manifest field must be an array or map: ' . $field);
                }
            }
            if (isset($data['description']) && ! is_string($data['description'])) {
                throw new RuntimeException('Module description must be a string.');
            }
            $manifest = ModuleManifest::fromArray($data);
            if ($manifest->id === 'core' || preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/D', $manifest->version) !== 1) {
                throw new RuntimeException('Package needs a non-Core id and a semantic release version.');
            }
            if (preg_match('/^MCMS\\\\Modules\\\\[A-Z][A-Za-z0-9_]*$/D', $manifest->namespace) !== 1
                || ! str_starts_with($manifest->provider, $manifest->namespace . '\\')
                || preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/D', $manifest->provider) !== 1) {
                throw new RuntimeException('Package must use a unique MCMS\\Modules\\Name namespace and its own provider.');
            }
            $providerFile = str_replace('\\', '/', substr($manifest->provider, strlen($manifest->namespace) + 1)) . '.php';
            if (! in_array($providerFile, $files, true)) {
                throw new RuntimeException('Module provider file is missing.');
            }
            (new VersionParser())->normalize($manifest->version);
            (new VersionParser())->parseConstraints($manifest->mcms);
            if (! array_is_list($manifest->requires)) {
                throw new RuntimeException('Module requires must be a list.');
            }
            foreach ($manifest->requires as $dependency) {
                $id = is_array($dependency) ? ($dependency['id'] ?? null) : $dependency;
                $constraint = is_array($dependency) ? ($dependency['version'] ?? '*') : '*';
                if (! is_string($id) || preg_match('/^[a-z][a-z0-9_-]*$/D', $id) !== 1 || ! is_string($constraint) || trim($constraint) === '') {
                    throw new RuntimeException('Invalid package dependency.');
                }
                (new VersionParser())->parseConstraints($constraint);
            }
            return ['manifest' => $manifest, 'files' => $files, 'bytes' => $bytes, 'sha256' => hash_file('sha256', $path)];
        } finally {
            $zip->close();
        }
    }

    private function checkCompatibility(ModuleManifest $manifest): void
    {
        $catalog = $this->modules->catalog();
        // Re-read package manifests inside the installation lock, even if the
        // request's module catalog was populated before another upload finished.
        foreach (glob($this->root . '/installed/*/module.json') ?: [] as $path) {
            $current = ModuleManifest::fromFile($path);
            $catalog[$current->id] = $current;
        }
        if (isset($catalog[$manifest->id])) {
            throw new RuntimeException('Module id already exists; package updates are not supported here.');
        }
        foreach ($catalog as $current) {
            if (strcasecmp($current->namespace, $manifest->namespace) === 0) {
                throw new RuntimeException('Module namespace already exists.');
            }
            if (array_intersect($current->publicPaths, $manifest->publicPaths) !== []) {
                throw new RuntimeException('Module public paths conflict with ' . $current->id . '.');
            }
        }
        if (array_intersect($manifest->publicPaths, ['admin', 'install', '_health', 'search', 'login', 'logout', 'register', 'auth', 'assets']) !== []) {
            throw new RuntimeException('Module public paths conflict with Core.');
        }
        $catalog[$manifest->id] = $manifest;
        $visit = function (ModuleManifest $current, array $stack) use (&$visit, $catalog): void {
            if (in_array($current->id, $stack, true)) {
                throw new RuntimeException('Circular package dependency.');
            }
            if (! Semver::satisfies(Version::VERSION, $current->mcms)) {
                throw new RuntimeException('Module ' . $current->id . ' requires mCMS ' . $current->mcms . '.');
            }
            foreach ($current->requires as $dependency) {
                $id = is_array($dependency) ? $dependency['id'] : $dependency;
                $constraint = is_array($dependency) ? ($dependency['version'] ?? '*') : '*';
                $required = $catalog[$id] ?? null;
                if ($required === null || ! Semver::satisfies($required->version, $constraint)) {
                    throw new RuntimeException('Missing or incompatible dependency: ' . $id . ' ' . $constraint . '.');
                }
                $visit($required, [...$stack, $current->id]);
            }
        };
        $visit($manifest, []);
    }

    private static function validatePath(string $name): void
    {
        if (strlen($name) > 240 || preg_match('~^(?:[A-Za-z0-9_-][A-Za-z0-9_. -]*/)*[A-Za-z0-9_-][A-Za-z0-9_. -]*/?$~D', $name) !== 1) {
            throw new RuntimeException('Unsafe package path. Use relative paths without dot segments or hidden files.');
        }
        foreach (explode('/', rtrim($name, '/')) as $part) {
            if (str_ends_with($part, '.') || str_ends_with($part, ' ')) {
                throw new RuntimeException('Ambiguous package path.');
            }
        }
        $top = strtolower(explode('/', $name)[0]);
        if (in_array($top, ['config', 'commands', 'vendor', 'node_modules'], true)) {
            throw new RuntimeException('Packages cannot include auto-discovered Config/Commands or bundled dependency directories.');
        }
    }

    private function pendingPath(string $token): string
    {
        if (preg_match('/^[a-f0-9]{48}$/D', $token) !== 1) {
            throw new RuntimeException('Invalid package preview.');
        }
        return $this->root . '/pending/' . $token . '.zip';
    }

    private function directory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new RuntimeException('Cannot create package directory.');
        }
    }
}
