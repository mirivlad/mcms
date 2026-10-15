<?php

declare(strict_types=1);

namespace MCMS\Core\Themes;

use MCMS\Core\Modules\ModulePackages;
use MCMS\Core\Modules\ModuleRuntime;
use MCMS\Core\Packages\ZipPackage;
use RuntimeException;
use Throwable;

/** Code-only swaps drain readers; a durable journal rolls interrupted swaps back. */
final class ThemeUpdates
{
    public function __construct(
        private readonly ThemeManager $themes,
        private readonly ThemePackages $packages,
        private readonly ModuleRuntime $runtime,
        private readonly string $root,
    ) {
    }

    public function prepare(string $id, string $archive): array
    {
        $this->recover();
        $current = $this->installed($id);
        $preview = $this->packages->prepare($archive, $id);
        return [...$preview, 'current' => $current->version, 'source' => ModulePackages::fingerprint($current->path)];
    }

    public function previous(string $id): ?array
    {
        $this->validId($id);
        $path = $this->root . '/updates/' . $id . '.json';
        return is_file($path) ? $this->read($path) : null;
    }

    public function apply(string $id, string $token, string $sha256, string $source): void
    {
        $this->runtime->exclusive(function () use ($id, $token, $sha256, $source): void {
            $this->runtime->assertAvailable();
            $this->recover();
            $this->installed($id);
            if (preg_match('/^[a-f0-9]{48}$/D', $token) !== 1) {
                throw new RuntimeException('Invalid theme preview.');
            }
            $archive = $this->root . '/pending/' . $token . '.zip';
            if (! is_file($archive) || filemtime($archive) < time() - 3600
                || ! hash_equals($sha256, (string) hash_file('sha256', $archive))) {
                throw new RuntimeException('Theme preview expired or archive changed. Upload it again.');
            }
            $preview = ThemePackages::inspectArchive($archive);
            $this->packages->checkCompatibility($preview['manifest'], $id);
            $stage = $this->root . '/pending/' . $token;
            $this->removeStage($stage);
            try {
                ZipPackage::extractArchive($archive, $stage, ThemePackages::inspectArchive(...));
                // Lint compiles templates without executing uploaded PHP.
                foreach ($preview['files'] as $file) {
                    if (! str_ends_with($file, '.php')) {
                        continue;
                    }
                    $process = proc_open([PHP_BINDIR . '/php', '-l', $stage . '/' . $file], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                    if (! is_resource($process)) {
                        throw new RuntimeException('Cannot validate theme PHP syntax.');
                    }
                    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    if (proc_close($process) !== 0) {
                        throw new RuntimeException('Invalid PHP template: ' . $file . ' ' . $output);
                    }
                }
                $candidateHash = ModulePackages::fingerprint($stage);
                foreach (glob($this->root . '/history/' . $id . '/*/theme.json') ?: [] as $path) {
                    if (ThemeManifest::fromFile($path, true)->version === $preview['manifest']->version
                        && ! hash_equals($candidateHash, ModulePackages::fingerprint(dirname($path)))) {
                        throw new RuntimeException('A retained version already has different bytes. Increase the theme version.');
                    }
                }
                $this->swap($id, $stage, $source, $preview['manifest']->version);
                unlink($archive);
            } finally {
                $this->removeStage($stage);
            }
        });
    }

    public function rollback(string $id, string $source): void
    {
        $this->runtime->exclusive(function () use ($id, $source): void {
            $this->runtime->assertAvailable();
            $this->recover();
            $this->installed($id);
            $previous = $this->previous($id);
            if ($previous === null || ! is_dir($previous['path'])
                || ! hash_equals($previous['fingerprint'], ModulePackages::fingerprint($previous['path']))) {
                throw new RuntimeException('Previous theme snapshot is unavailable or changed.');
            }
            $this->swap($id, $previous['path'], $source, $previous['version']);
        });
    }

    private function swap(string $id, string $stage, string $source, string $version): void
    {
        $current = $this->installed($id);
        if (! hash_equals($source, ModulePackages::fingerprint($current->path))) {
            throw new RuntimeException('Installed theme changed. Review the update again.');
        }
        $operation = bin2hex(random_bytes(16));
        $previous = $this->root . '/history/' . $id . '/' . $operation;
        $this->directory(dirname($previous));
        $journal = $this->root . '/transactions/' . $id . '.json';
        $state = ['id' => $id, 'installed' => $current->path, 'previous' => $previous,
            'stage' => $stage, 'receipt' => $this->previous($id)];
        $this->write($journal, $state);
        try {
            if (! rename($current->path, $previous) || ! rename($stage, $current->path)) {
                throw new RuntimeException('Cannot publish updated theme code.');
            }
            $this->invalidate($current->path);
            $this->write($this->root . '/updates/' . $id . '.json', [
                'path' => $previous, 'version' => $current->version,
                'fingerprint' => $source, 'current' => $version,
            ]);
            if (! unlink($journal)) {
                throw new RuntimeException('Cannot finish theme update.');
            }
            $this->themes->refresh();
        } catch (Throwable $e) {
            $this->recover();
            throw $e;
        }
    }

    public function recover(): void
    {
        if ((glob($this->root . '/transactions/*.json') ?: []) === []) {
            return;
        }
        $this->runtime->exclusive(function (): void {
            clearstatcache(true);
            foreach (glob($this->root . '/transactions/*.json') ?: [] as $journal) {
                $state = $this->read($journal);
                if (is_dir($state['previous'])) {
                    if (is_dir($state['installed'])) {
                        // Rollback candidates came from history. Restore that exact
                        // path so the previous receipt remains valid after recovery.
                        $failed = ! file_exists($state['stage']) && ! is_link($state['stage'])
                            ? $state['stage']
                            : $this->root . '/history/' . $state['id'] . '/' . bin2hex(random_bytes(16));
                        if (! rename($state['installed'], $failed)) {
                            throw new RuntimeException('Cannot retain interrupted theme candidate.');
                        }
                    }
                    if (! rename($state['previous'], $state['installed'])) {
                        throw new RuntimeException('Cannot restore interrupted theme update.');
                    }
                    $this->invalidate($state['installed']);
                }
                $receipt = $this->root . '/updates/' . $state['id'] . '.json';
                if ($state['receipt'] !== null) {
                    $this->write($receipt, $state['receipt']);
                } elseif (is_file($receipt)) {
                    unlink($receipt);
                }
                unlink($journal);
                $this->themes->refresh();
            }
        });
    }

    /** Old pages may still request immutable assets after update/rollback. */
    public function assetTheme(string $id, string $version): ?ThemeManifest
    {
        if (preg_match('/^[a-z][a-z0-9-]*$/D', $id) !== 1) {
            return null;
        }
        $current = $this->themes->find($id);
        if ($current === null || ! $current->external) {
            return null;
        }
        if ($current->version === $version) {
            return $current;
        }
        foreach (glob($this->root . '/history/' . $id . '/*/theme.json') ?: [] as $path) {
            $manifest = ThemeManifest::fromFile($path, true);
            if ($manifest->version === $version) {
                return $manifest;
            }
        }
        return null;
    }

    private function installed(string $id): ThemeManifest
    {
        $this->validId($id);
        $this->themes->refresh();
        $theme = $this->themes->find($id);
        if ($theme === null || ! $theme->external || $theme->path !== $this->root . '/installed/' . $id) {
            throw new RuntimeException('Only installed external themes can be updated.');
        }
        return $theme;
    }

    private function validId(string $id): void
    {
        if (preg_match('/^[a-z][a-z0-9-]*$/D', $id) !== 1) {
            throw new RuntimeException('Invalid theme id.');
        }
    }

    private function removeStage(string $path): void
    {
        if (is_link($path)) {
            throw new RuntimeException('Theme staging path is a link.');
        }
        if (! is_dir($path)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() && ! $file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($path);
    }

    private function invalidate(string $path): void
    {
        clearstatcache(true);
        if (function_exists('opcache_invalidate')) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->getExtension() === 'php') {
                    opcache_invalidate($file->getPathname(), true);
                }
            }
        }
    }

    private function read(string $path): array
    {
        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    private function write(string $path, array $data): void
    {
        $this->directory(dirname($path));
        $temp = tempnam(dirname($path), 'theme-');
        if ($temp === false || file_put_contents($temp, json_encode($data, JSON_THROW_ON_ERROR)) === false || ! rename($temp, $path)) {
            throw new RuntimeException('Cannot persist theme update.');
        }
    }

    private function directory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new RuntimeException('Cannot create theme update directory.');
        }
    }
}
