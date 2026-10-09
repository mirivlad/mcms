<?php

declare(strict_types=1);

namespace MCMS\Core\Modules;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use MCMS\Core\Version;
use MCMS\Core\Packages\ZipPackage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

/** Packages contain trusted executable PHP, but inspection never loads it. */
final class ModulePackages
{
    public const MAX_ARCHIVE_BYTES = ZipPackage::MAX_ARCHIVE_BYTES;
    private const PREVIEW_TTL = 3600;

    public function __construct(private readonly ModuleManager $modules, private readonly string $root, private readonly ?ModuleRuntime $runtime = null)
    {
    }

    public static function pack(string $source, string $output): void
    {
        ZipPackage::pack($source, $output, 'module.json', self::inspectArchive(...));
    }

    public function prepare(string $archive, ?string $replaceId = null): array
    {
        $this->runtime?->assertAvailable();
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
            $this->checkCompatibility($preview['manifest'], $replaceId);
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
        if ($this->runtime !== null && ! $this->runtime->isExclusive()) {
            return $this->runtime->exclusive(fn () => $this->install($token, $sha256));
        }
        $this->runtime?->assertAvailable();
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
        $preview = ZipPackage::inspectArchive($path, 'module.json');
        foreach ($preview['entries'] as $name) {
            self::validatePath($name);
        }
        $data = $preview['data'];
        $files = $preview['files'];
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
        return ['manifest' => $manifest, 'files' => $files, 'bytes' => $preview['bytes'], 'sha256' => $preview['sha256']];
    }

    public function checkCompatibility(ModuleManifest $manifest, ?string $replaceId = null): void
    {
        $catalog = $this->modules->catalog();
        // Re-read package manifests inside the installation lock, even if the
        // request's module catalog was populated before another upload finished.
        foreach (glob($this->root . '/installed/*/module.json') ?: [] as $path) {
            $current = ModuleManifest::fromFile($path);
            $catalog[$current->id] = $current;
        }
        if ($replaceId !== null) {
            $existing = $catalog[$replaceId] ?? null;
            if ($existing === null || $replaceId !== $manifest->id
                || $existing->namespace !== $manifest->namespace || $existing->provider !== $manifest->provider
                || realpath($existing->path) !== realpath($this->root . '/installed/' . substr($existing->namespace, strlen('MCMS\\Modules\\')))
                || ! str_starts_with($existing->namespace, 'MCMS\\Modules\\')) {
                throw new RuntimeException('Only an external package with the same id, namespace and provider can be updated.');
            }
            if (! \Composer\Semver\Comparator::greaterThan($manifest->version, $existing->version)) {
                throw new RuntimeException('Update version must be newer than the current package.');
            }
            unset($catalog[$replaceId]);
        } elseif (isset($catalog[$manifest->id])) {
            throw new RuntimeException('Module id already exists; package updates are not supported here.');
        }
        foreach ($catalog as $current) {
            if (strcasecmp($current->namespace, $manifest->namespace) === 0) {
                throw new RuntimeException('Module namespace already exists.');
            }
            if (array_intersect($current->publicPaths, $manifest->publicPaths) !== []) {
                throw new RuntimeException('Module public paths conflict with ' . $current->id . '.');
            }
            if ($replaceId !== null) {
                foreach ($current->requires as $dependency) {
                    $id = is_array($dependency) ? $dependency['id'] : $dependency;
                    $constraint = is_array($dependency) ? ($dependency['version'] ?? '*') : '*';
                    if ($id === $replaceId && ! Semver::satisfies($manifest->version, $constraint)) {
                        throw new RuntimeException('Update is incompatible with dependent module ' . $current->id . '.');
                    }
                }
            }
        }
        if (array_intersect($manifest->publicPaths, ['admin', 'install', '_health', 'search', 'login', 'logout', 'register', 'auth', 'assets', 'theme-assets']) !== []) {
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
        ZipPackage::validatePath($name);
        $top = strtolower(explode('/', $name)[0]);
        if (in_array($top, ['config', 'commands', 'vendor', 'node_modules'], true)) {
            throw new RuntimeException('Packages cannot include auto-discovered Config/Commands or bundled dependency directories.');
        }
    }

    public function pendingArchive(string $token, string $sha256): string
    {
        $path = $this->pendingPath($token);
        if (! is_file($path) || filemtime($path) < time() - self::PREVIEW_TTL
            || ! hash_equals($sha256, (string) hash_file('sha256', $path))) {
            throw new RuntimeException('Package preview expired or the archive changed. Upload it again.');
        }
        return $path;
    }

    public static function extractArchive(string $archive, string $destination): ModuleManifest
    {
        ZipPackage::extractArchive($archive, $destination, self::inspectArchive(...));
        return ModuleManifest::fromFile($destination . '/module.json');
    }

    public static function fingerprint(string $directory): string
    {
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isLink() || ! $file->isFile()) {
                throw new RuntimeException('Package source contains a link or special file.');
            }
            $name = substr($file->getPathname(), strlen($directory) + 1);
            self::validatePath($name);
            $files[$name] = hash_file('sha256', $file->getPathname());
        }
        ksort($files);
        return hash('sha256', json_encode($files, JSON_THROW_ON_ERROR));
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
