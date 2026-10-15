<?php

declare(strict_types=1);

namespace MCMS\Core\Themes;

use Composer\Semver\Semver;
use MCMS\Core\Modules\ModuleRuntime;
use MCMS\Core\Packages\ZipPackage;
use MCMS\Core\Version;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

/** Installation does not activate or execute the uploaded theme. */
final class ThemePackages
{
    public const MAX_ARCHIVE_BYTES = ZipPackage::MAX_ARCHIVE_BYTES;
    private const PREVIEW_TTL = 3600;
    public const ASSET_TYPES = [
        'css' => 'text/css', 'js' => 'text/javascript', 'png' => 'image/png',
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
        'webp' => 'image/webp', 'avif' => 'image/avif', 'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon', 'woff' => 'font/woff', 'woff2' => 'font/woff2',
        'ttf' => 'font/ttf', 'otf' => 'font/otf',
    ];

    public function __construct(private readonly ThemeManager $themes, private readonly string $root, private readonly ?ModuleRuntime $runtime = null)
    {
    }

    public static function pack(string $source, string $output): void
    {
        ZipPackage::pack($source, $output, 'theme.json', self::inspectArchive(...));
    }

    public static function inspectArchive(string $archive): array
    {
        $preview = ZipPackage::inspectArchive($archive, 'theme.json');
        $data = $preview['data'];
        if (! isset($data['mcms']) || ! is_string($data['mcms']) || trim($data['mcms']) === '') {
            throw new RuntimeException('Theme package requires an explicit mcms version constraint.');
        }
        $manifest = ThemeManifest::fromArray($data, '', true);
        if (in_array($manifest->id, ['package', 'images'], true)) {
            throw new RuntimeException('Theme id conflicts with a Core administration route.');
        }
        if (preg_match('/^MCMS\\\\Themes\\\\[A-Z][A-Za-z0-9_]*$/D', $manifest->namespace) !== 1) {
            throw new RuntimeException('Theme package must use its own MCMS\\Themes\\Name namespace.');
        }
        foreach ($preview['entries'] as $name) {
            $directory = str_ends_with($name, '/');
            if ($directory && ($name === 'Views/' || $name === 'assets/' || str_starts_with($name, 'Views/') || str_starts_with($name, 'assets/'))) {
                continue;
            }
            if (! $directory && (in_array($name, ['theme.json', 'README.md', 'LICENSE', 'LICENSE.md', 'LICENSE.txt'], true)
                || (str_starts_with($name, 'Views/') && str_ends_with($name, '.php'))
                || (str_starts_with($name, 'assets/') && isset(self::ASSET_TYPES[pathinfo($name, PATHINFO_EXTENSION)])))) {
                continue;
            }
            throw new RuntimeException('Theme packages contain only theme.json, Views/*.php, static assets and optional README/license files.');
        }
        if (! isset($manifest->templates['home'])) {
            throw new RuntimeException('Theme package requires a home template.');
        }
        foreach ($manifest->templates as $view) {
            if (! in_array('Views/' . $view . '.php', $preview['files'], true)) {
                throw new RuntimeException('Declared theme template is missing: ' . $view);
            }
        }
        if ($manifest->screenshot !== null && (! in_array('assets/' . $manifest->screenshot, $preview['files'], true)
            || ! str_starts_with(self::ASSET_TYPES[pathinfo($manifest->screenshot, PATHINFO_EXTENSION)] ?? '', 'image/'))) {
            throw new RuntimeException('Theme screenshot must be an existing image in assets/.');
        }
        // External packages have no Language PHP; their labels are plain text.
        foreach ([$manifest->name, $manifest->description, ...array_column($manifest->menuLocations, 'label'), ...array_column($manifest->menuLocations, 'description'), ...array_values($manifest->homeDefaults)] as $text) {
            if (is_string($text) && str_starts_with($text, '@')) {
                throw new RuntimeException('External theme labels must be plain text.');
            }
        }
        return ['manifest' => $manifest, 'files' => $preview['files'], 'bytes' => $preview['bytes'], 'sha256' => $preview['sha256']];
    }

    public function prepare(string $archive, ?string $replacingId = null): array
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
            throw new RuntimeException('Cannot stage theme package; maximum archive size is 20 MiB.');
        }
        chmod($path, 0600);
        try {
            $preview = self::inspectArchive($path);
            $this->checkCompatibility($preview['manifest'], $replacingId);
            return [...$preview, 'token' => $token];
        } catch (Throwable $e) {
            unlink($path);
            throw $e;
        }
    }

    public function checkCompatibility(ThemeManifest $manifest, ?string $replacingId = null): void
    {
        if (! Semver::satisfies(Version::VERSION, $manifest->mcms)) {
            throw new RuntimeException('Theme requires mCMS ' . $manifest->mcms . '.');
        }
        $catalog = $this->themes->catalog();
        // The manager may have cached the catalog before another installation.
        foreach (glob($this->root . '/installed/*/theme.json') ?: [] as $path) {
            $current = ThemeManifest::fromFile($path, true);
            $catalog[$current->id] = $current;
        }
        if ($replacingId !== null) {
            $installed = $catalog[$replacingId] ?? null;
            if ($installed === null || ! $installed->external || $installed->id !== $manifest->id
                || $installed->namespace !== $manifest->namespace
                || ! \Composer\Semver\Comparator::greaterThan($manifest->version, $installed->version)) {
                throw new RuntimeException('Update requires the same external theme id/namespace and a strictly newer version.');
            }
        }
        foreach ($catalog as $current) {
            if ($current->id === $replacingId) {
                continue;
            }
            if ($current->id === $manifest->id || strcasecmp($current->namespace, $manifest->namespace) === 0) {
                throw new RuntimeException('Theme id or namespace already exists. Package replacement is not supported.');
            }
        }
    }

    public function install(string $token, string $sha256): ThemeManifest
    {
        if ($this->runtime !== null && ! $this->runtime->isExclusive()) {
            return $this->runtime->exclusive(fn () => $this->install($token, $sha256));
        }
        $this->runtime?->assertAvailable();
        $path = $this->pendingPath($token);
        $this->directory($this->root . '/installed');
        $lock = fopen($this->root . '/install.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException('Cannot lock theme installation.');
        }
        $stage = $this->root . '/pending/' . $token;
        $ownsStage = false;
        try {
            if (! is_file($path) || filemtime($path) < time() - self::PREVIEW_TTL
                || ! hash_equals($sha256, (string) hash_file('sha256', $path))) {
                throw new RuntimeException('Theme preview expired or archive changed. Upload it again.');
            }
            $preview = self::inspectArchive($path);
            $manifest = $preview['manifest'];
            $this->checkCompatibility($manifest);
            $destination = $this->root . '/installed/' . $manifest->id;
            if (file_exists($destination) || is_link($destination)) {
                throw new RuntimeException('Theme installation directory already exists.');
            }
            if (file_exists($stage) || is_link($stage)) {
                throw new RuntimeException('Theme staging directory already exists.');
            }
            $ownsStage = true;
            ZipPackage::extractArchive($path, $stage, self::inspectArchive(...));
            if (! rename($stage, $destination)) {
                throw new RuntimeException('Cannot publish theme directory.');
            }
            unlink($path);
            return ThemeManifest::fromFile($destination . '/theme.json', true);
        } finally {
            if ($ownsStage && is_dir($stage)) {
                foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, \FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                    $file->isDir() && ! $file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
                }
                rmdir($stage);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function cancel(string $token): void
    {
        $path = $this->pendingPath($token);
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function pendingPath(string $token): string
    {
        if (preg_match('/^[a-f0-9]{48}$/D', $token) !== 1) {
            throw new RuntimeException('Invalid theme preview.');
        }
        return $this->root . '/pending/' . $token . '.zip';
    }

    private function directory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new RuntimeException('Cannot create theme package directory.');
        }
    }
}
