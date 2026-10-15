<?php

declare(strict_types=1);

namespace MCMS\Core\Packages;

use Closure;
use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModulePackages;
use MCMS\Core\Modules\ModuleUpdates;
use MCMS\Core\Themes\ThemeManager;
use MCMS\Core\Themes\ThemePackages;
use MCMS\Core\Themes\ThemeUpdates;
use MCMS\Core\Version;
use RuntimeException;
use Throwable;

/** Cached information/download source; all package mutations remain in Core services. */
final class ExtensionCatalog
{
    public function __construct(
        private readonly string $root,
        private readonly string $moduleRoot,
        private readonly string $themeRoot,
        private readonly ModuleManager $modules,
        private readonly ThemeManager $themes,
        private readonly ModulePackages $modulePackages,
        private readonly ThemePackages $themePackages,
        private readonly ModuleUpdates $moduleUpdates,
        private readonly ThemeUpdates $themeUpdates,
        private readonly ?Closure $fetcher = null,
    ) {
    }

    public function cache(): array
    {
        try {
            $cache = PackageState::read($this->root . '/cache.json');
            if ($cache === null) {
                return ['extensions' => [], 'checked_at' => null];
            }
            CatalogSchema::index(json_encode($cache, JSON_THROW_ON_ERROR));
            return $cache;
        } catch (Throwable) {
            return ['extensions' => [], 'checked_at' => null, 'error' => 'Catalog cache is invalid. Refresh it.'];
        }
    }

    public function status(): array
    {
        try {
            return PackageState::read($this->root . '/status.json') ?? [];
        } catch (Throwable) {
            return ['error' => 'Catalog status cache is invalid. Refresh it.'];
        }
    }

    public function due(): bool
    {
        return (int) ($this->cache()['checked_at'] ?? 0) < time() - 86400
            && (int) ($this->status()['attempted_at'] ?? 0) < time() - 3600;
    }

    public function refresh(bool $force = false): array
    {
        if (! is_dir($this->root) && ! mkdir($this->root, 0700, true) && ! is_dir($this->root)) {
            throw new RuntimeException('Cannot create catalog cache.');
        }
        $lock = fopen($this->root . '/refresh.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('Cannot open catalog refresh lock.');
        }
        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return $this->cache();
        }
        try {
            if ((! $force && ! $this->due()) || ($force && (int) ($this->status()['attempted_at'] ?? 0) > time() - 60)) {
                return $this->cache();
            }
            // Persist before networking: parallel requests and failures cannot cause a retry storm.
            PackageState::write($this->root . '/status.json', ['attempted_at' => time(), 'error' => null]);
            try {
                $data = CatalogSchema::index($this->fetch(GitHubCatalogSource::BASE . 'index.json', CatalogSchema::MAX_INDEX_BYTES));
                PackageState::write($this->root . '/cache.json', [...$data, 'checked_at' => time()]);
            } catch (Throwable $e) {
                PackageState::write($this->root . '/status.json', ['attempted_at' => time(), 'error' => $e->getMessage()]);
                throw $e;
            }
            return $this->cache();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function entries(): array
    {
        $this->modules->refresh();
        $this->themes->refresh();
        $moduleCatalog = $this->modules->catalog();
        $themeCatalog = $this->themes->catalog();
        $states = $this->modules->states();
        $entries = [];
        foreach ($this->cache()['extensions'] as $entry) {
            $installed = ($entry['type'] === 'module' ? $moduleCatalog : $themeCatalog)[$entry['id']] ?? null;
            $external = $installed === null || realpath(dirname($installed->path)) === realpath($this->packageRoot($entry['type']) . '/installed');
            $retained = PackageState::read($this->packageRoot($entry['type']) . '/retained/' . $entry['id'] . '.json');
            $available = null;
            $reason = null;
            $releases = $entry['versions'];
            usort($releases, fn ($a, $b) => version_compare($b['version'], $a['version']));
            foreach ($releases as $release) {
                $error = $this->compatibility($release, $moduleCatalog, $entry['type'] === 'module' ? $entry['id'] : null);
                if (! $external) {
                    $error = 'This ID belongs to a built-in extension.';
                }
                if ($entry['type'] === 'module' && $installed === null && $retained !== null
                    && Comparator::lessThan($release['version'], $retained['schema_version'])) {
                    $error = 'Release is older than the retained data schema.';
                }
                if ($error === null && $available === null) {
                    $available = $release;
                }
                $reason ??= $error;
            }
            $update = $installed !== null && $available !== null && Comparator::greaterThan($available['version'], $installed->version);
            $entry += [
                'installed' => $installed?->version, 'available' => $available,
                'compatible' => $available !== null, 'reason' => $available === null ? $reason : null, 'update' => $update,
                'enabled' => $entry['type'] === 'module' ? (int) ($states[$entry['id']]['enabled'] ?? 0) === 1 : $this->themes->activeId() === $entry['id'],
                'retained' => $installed === null && $retained !== null,
            ];
            $entry['state'] = $installed === null ? ($entry['retained'] ? 'retained' : 'available') : ($update ? 'update' : 'installed');
            $entries[$entry['id']] = $entry;
        }
        return $entries;
    }

    public function updateCount(?string $type = null): int
    {
        return count(array_filter($this->entries(), fn ($entry) => $entry['update'] && ($type === null || $entry['type'] === $type)));
    }

    public function prepare(string $id, string $version): array
    {
        $entry = $this->entries()[$id] ?? null;
        $release = $entry === null ? null : (array_values(array_filter($entry['versions'], fn ($row) => $row['version'] === $version))[0] ?? null);
        if ($release === null) {
            throw new RuntimeException('Unknown catalog package/version. Refresh the catalog.');
        }
        $reason = $this->compatibility($release, $this->modules->catalog(), $entry['type'] === 'module' ? $entry['id'] : null);
        if ($reason !== null) {
            throw new RuntimeException($reason);
        }
        $bytes = $this->fetch($release['download'], $release['bytes']);
        if (strlen($bytes) !== $release['bytes'] || ! hash_equals($release['sha256'], hash('sha256', $bytes))) {
            throw new RuntimeException('Catalog archive size or SHA-256 does not match.');
        }
        if (! is_dir($this->root) && ! mkdir($this->root, 0700, true) && ! is_dir($this->root)) {
            throw new RuntimeException('Cannot create catalog staging directory.');
        }
        $archive = tempnam($this->root, 'download-');
        try {
            if ($archive === false || file_put_contents($archive, $bytes) !== strlen($bytes)) {
                throw new RuntimeException('Cannot stage catalog archive.');
            }
            chmod($archive, 0600);
            $inspection = $entry['type'] === 'module' ? ModulePackages::inspectArchive($archive) : ThemePackages::inspectArchive($archive);
            CatalogSchema::assertPackage($entry, $release, $inspection['manifest']);
            $updating = $entry['installed'] !== null;
            $preview = match ($entry['type']) {
                'module' => $updating ? $this->moduleUpdates->prepare($id, $archive) : $this->modulePackages->prepare($archive),
                'theme' => $updating ? $this->themeUpdates->prepare($id, $archive) : $this->themePackages->prepare($archive),
            };
            return [...$preview, 'type' => $entry['type'], 'action' => $updating ? 'update' : 'install'];
        } finally {
            if (is_string($archive)) {
                PackageState::remove($archive);
            }
        }
    }

    private function compatibility(array $release, array $modules, ?string $moduleId = null): ?string
    {
        if (! Semver::satisfies(Version::VERSION, $release['mcms'])) {
            return 'Requires mCMS ' . $release['mcms'];
        }
        foreach ($release['requires'] as $dependency) {
            $id = is_array($dependency) ? $dependency['id'] : $dependency;
            $constraint = is_array($dependency) ? ($dependency['version'] ?? '*') : '*';
            if (! isset($modules[$id]) || ! Semver::satisfies($modules[$id]->version, $constraint)) {
                return 'Missing/incompatible dependency: ' . $id . ' ' . $constraint;
            }
        }
        if ($moduleId !== null) {
            foreach ($modules as $other) {
                if ($other->id === $moduleId) {
                    continue;
                }
                foreach ($other->requires as $dependency) {
                    $id = is_array($dependency) ? $dependency['id'] : $dependency;
                    $constraint = is_array($dependency) ? ($dependency['version'] ?? '*') : '*';
                    if ($id === $moduleId && ! Semver::satisfies($release['version'], $constraint)) {
                        return 'Incompatible with installed dependent: ' . $other->id . ' (' . $constraint . ')';
                    }
                }
            }
        }
        return null;
    }

    private function packageRoot(string $type): string
    {
        return $type === 'module' ? $this->moduleRoot : $this->themeRoot;
    }

    private function fetch(string $url, int $limit): string
    {
        GitHubCatalogSource::assertUrl($url, true);
        $body = $this->fetcher !== null ? ($this->fetcher)($url, $limit) : (new GitHubCatalogSource())->fetch($url, $limit);
        if (strlen($body) > $limit) {
            throw new RuntimeException('Catalog download exceeds size limit.');
        }
        return $body;
    }
}
