<?php

declare(strict_types=1);

namespace MCMS\Core\Modules;

use RuntimeException;
use Throwable;

/** Explicit updates of installed ZIP packages, with manual database recovery. */
final class ModuleUpdates
{
    public function __construct(
        private readonly ModuleManager $modules,
        private readonly ModulePackages $packages,
        private readonly ModuleRuntime $runtime,
        private readonly string $root,
    ) {
    }

    public function state(string $id): ?array
    {
        $file = $this->directory($id) . '/state.json';
        if (! is_file($file)) {
            return null;
        }
        $state = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($state) || ($state['id'] ?? null) !== $id) {
            throw new RuntimeException('Invalid module update receipt.');
        }
        return $state;
    }

    public function prepare(string $id, string $archive): array
    {
        return $this->runtime->exclusive(function () use ($id, $archive): array {
            $this->runtime->assertAvailable();
            $this->assertDisabled($id);
            $this->assertNoPending($id);
            return $this->packages->prepare($archive, $id);
        });
    }

    public function queue(string $id, string $token, string $sha256): array
    {
        return $this->runtime->exclusive(function () use ($id, $token, $sha256): array {
            $this->runtime->assertAvailable();
            $old = $this->assertDisabled($id);
            $this->assertNoPending($id);
            $archive = $this->packages->pendingArchive($token, $sha256);
            $preview = ModulePackages::inspectArchive($archive);
            $this->packages->checkCompatibility($preview['manifest'], $id);
            $directory = $this->directory($id);
            if (is_dir($directory)) {
                $history = $this->root . '/history';
                if (! is_dir($history) && ! mkdir($history, 0755, true)) {
                    throw new RuntimeException('Cannot create update history.');
                }
                if (! rename($directory, $history . '/' . $id . '-' . bin2hex(random_bytes(12)))) {
                    throw new RuntimeException('Cannot retain previous update snapshot.');
                }
            }
            mkdir($directory, 0755, true);
            try {
                ModulePackages::extractArchive($archive, $directory . '/candidate');
                $this->assertMigrationsPreserved($old->path, $directory . '/candidate');
                ModulePackages::pack($old->path, $directory . '/previous.zip');
                $state = [
                    'id' => $id, 'operation' => bin2hex(random_bytes(16)), 'status' => 'queued',
                    'from' => $old->version, 'to' => $preview['manifest']->version,
                    'folder' => substr($old->namespace, strlen('MCMS\\Modules\\')),
                    'namespace' => $old->namespace, 'source' => ModulePackages::fingerprint($old->path),
                    'candidate' => ModulePackages::fingerprint($directory . '/candidate'),
                    'php_files' => array_values(array_unique([...$this->phpFiles($old->path), ...$this->phpFiles($directory . '/candidate')])),
                    'archive_sha256' => $sha256,
                    'previous_sha256' => hash_file('sha256', $directory . '/previous.zip'),
                    'migrations' => $this->modules->migrationFingerprint($old->namespace),
                    'created_at' => date(DATE_ATOM), 'error' => null,
                ];
                $this->write($state);
                $this->packages->cancel($token);
                return $state;
            } catch (Throwable $e) {
                $this->removeTree($directory);
                throw $e;
            }
        });
    }

    public function cancel(string $id, string $operation): void
    {
        $this->runtime->exclusive(function () use ($id, $operation): void {
            $this->runtime->assertAvailable();
            $this->receipt($id, $operation, ['queued']);
            $this->removeTree($this->directory($id));
        });
    }

    /** Persists maintenance before launching a fresh process that can load new PHP. */
    public function begin(string $id, string $operation, string $action): void
    {
        $this->runtime->exclusive(function () use ($id, $operation, $action): void {
            if ($action === 'apply') {
                $this->runtime->assertAvailable();
                $state = $this->receipt($id, $operation, ['queued']);
                $this->validateSource($state);
                $this->runtime->beginMaintenance($id, $operation, $action);
                $state['status'] = 'applying';
            } elseif ($action === 'restore') {
                $state = $this->receipt($id, $operation, ['queued', 'applying', 'failed', 'restoring']);
                $flag = $this->runtime->maintenance();
                if (($flag['id'] ?? null) !== $id || ($flag['operation'] ?? null) !== $operation) {
                    throw new RuntimeException('Update does not own maintenance.');
                }
                $this->assertDatabaseRestored($state);
                $state['status'] = 'restoring';
            } else {
                throw new RuntimeException('Invalid module update action.');
            }
            $this->write($state);
        });
    }

    public function runWorker(string $id, string $operation, string $action, bool $webWorker = false): void
    {
        if (! is_cli() && ! ($webWorker && \MCMS\Core\Install\SharedHostSetup::enabled())) {
            throw new RuntimeException('Module update worker requires CLI.');
        }
        $this->runtime->exclusive(function () use ($id, $operation, $action): void {
            $state = $this->receipt($id, $operation, $action === 'apply' ? ['applying'] : ['restoring']);
            $this->runtime->asWorker($id, $operation, function () use ($id, $action, &$state): void {
                try {
                    $directory = $this->directory($id);
                    $installed = $this->installed($state);
                    if ($action === 'apply') {
                        $this->validateSource($state);
                        if (! rename($installed, $directory . '/previous')) {
                            throw new RuntimeException('Cannot retain installed module code.');
                        }
                        if (! rename($directory . '/candidate', $installed)) {
                            throw new RuntimeException('Cannot publish updated module code.');
                        }
                        // Persist the point after which target PHP/DDL can have run.
                        $state['code_changed'] = true;
                        $this->write($state);
                        if (method_exists(service('locator'), 'deleteCache')) {
                            service('locator')->deleteCache();
                            \Config\Services::resetSingle('locator');
                        }
                        $this->modules->migrateDisabled($id);
                        $state['status'] = 'applied';
                    } elseif ($action === 'restore') {
                        $this->assertDatabaseRestored($state);
                        if (is_dir($directory . '/restore') && ModulePackages::fingerprint($directory . '/restore') !== $state['source']) {
                            $this->removeTree($directory . '/restore');
                        }
                        if (! is_dir($directory . '/restore')) {
                            if (! hash_equals($state['previous_sha256'], (string) hash_file('sha256', $directory . '/previous.zip'))) {
                                throw new RuntimeException('Previous code snapshot changed.');
                            }
                            ModulePackages::extractArchive($directory . '/previous.zip', $directory . '/restore');
                        }
                        if (is_dir($installed)) {
                            $this->removeTree($installed);
                        }
                        if (! rename($directory . '/restore', $installed)) {
                            throw new RuntimeException('Cannot restore previous module code.');
                        }
                        $state['status'] = 'restored';
                    } else {
                        throw new RuntimeException('Invalid worker action.');
                    }
                    $state['error'] = null;
                    $this->write($state);
                } catch (Throwable $e) {
                    $state['status'] = 'failed';
                    $state['error'] = $e->getMessage();
                    $this->write($state);
                    throw $e;
                }
            });
        });
    }

    /** Called by FPM before module boot; CLI opcache is a separate cache. */
    public function finalize(): void
    {
        $flag = $this->runtime->maintenance();
        if ($flag === null || ! is_string($flag['id'] ?? null)) {
            return;
        }
        $state = $this->state($flag['id']);
        if ($state === null || ! in_array($state['status'], ['applied', 'restored'], true)) {
            return;
        }
        $this->runtime->exclusive(function () use ($state): void {
            if ($this->runtime->maintenance() === null) {
                return;
            }
            foreach ($state['php_files'] as $file) {
                $path = $this->installed($state) . '/' . $file;
                if (function_exists('opcache_is_script_cached') && opcache_is_script_cached($path)
                    && ! opcache_invalidate($path, true)) {
                    throw new RuntimeException('Restart PHP before finishing module maintenance.');
                }
            }
            if (method_exists(service('locator'), 'deleteCache')) {
                service('locator')->deleteCache();
            }
            $this->runtime->finishMaintenance($state['id'], $state['operation']);
        });
    }

    public function launch(string $id, string $operation, string $action): void
    {
        $directory = $this->directory($id);
        $this->runtime->release();
        if (\MCMS\Core\Install\SharedHostSetup::enabled()) {
            // The module-update route excludes module provider boot; this
            // request can safely publish the staged package in maintenance.
            $this->runWorker($id, $operation, $action, true);
            $this->finalize();
            if ($this->runtime->maintenance() !== null) {
                throw new RuntimeException('Module update requires recovery.');
            }
            return;
        }
        $php = PHP_BINDIR . '/php';
        $process = proc_open([$php, ROOTPATH . 'spark', 'mcms:module:update', $id, $operation, $action], [
            0 => ['file', '/dev/null', 'r'], 1 => ['file', $directory . '/worker.log', 'a'],
            2 => ['file', $directory . '/worker.log', 'a'],
        ], $pipes, ROOTPATH);
        if (! is_resource($process)) {
            throw new RuntimeException('Cannot start module update worker; recovery remains available.');
        }
        $exit = proc_close($process);
        $this->finalize();
        if ($exit !== 0 || $this->runtime->maintenance() !== null) {
            throw new RuntimeException($this->state($id)['error'] ?? 'Module update was interrupted. Restore the database backup before restoring code.');
        }
    }

    public function snapshot(string $id): string
    {
        $state = $this->state($id);
        if ($state === null) {
            throw new RuntimeException('No previous module snapshot.');
        }
        $path = $this->directory($id) . '/previous.zip';
        if (! is_file($path) || ! hash_equals($state['previous_sha256'], (string) hash_file('sha256', $path))) {
            throw new RuntimeException('Previous module snapshot changed.');
        }
        return $path;
    }

    private function validateSource(array $state): void
    {
        $old = $this->assertDisabled($state['id']);
        $directory = $this->directory($state['id']);
        if ($old->version !== $state['from'] || ! hash_equals($state['source'], ModulePackages::fingerprint($old->path))
            || ! hash_equals($state['candidate'], ModulePackages::fingerprint($directory . '/candidate'))
            || $this->modules->migrationFingerprint($old->namespace) !== $state['migrations']) {
            throw new RuntimeException('Module code or migrations changed after update preparation. Cancel and prepare again.');
        }
        $this->assertMigrationsPreserved($old->path, $directory . '/candidate');
        $candidate = ModuleManifest::fromFile($directory . '/candidate/module.json');
        $this->packages->checkCompatibility($candidate, $state['id']);
        $states = $this->modules->states();
        foreach ($candidate->requires as $dependency) {
            $id = is_array($dependency) ? $dependency['id'] : $dependency;
            if ((int) ($states[$id]['enabled'] ?? 0) !== 1
                || ($states[$id]['version'] ?? null) !== ($this->modules->catalog()[$id]->version ?? null)) {
                throw new RuntimeException('Enable the update dependency first: ' . $id);
            }
        }
    }

    private function assertDatabaseRestored(array $state): void
    {
        $installed = $this->modules->states()[$state['id']] ?? null;
        if ($installed === null || (int) $installed['enabled'] !== 0 || $installed['version'] !== $state['from']
            || $this->modules->migrationFingerprint($state['namespace']) !== $state['migrations']) {
            throw new RuntimeException('Restore the database backup made before this update first. Registry/migration history does not match.');
        }
    }

    private function assertDisabled(string $id): ModuleManifest
    {
        $manifest = $this->modules->catalog()[$id] ?? null;
        $state = $this->modules->states()[$id] ?? null;
        if ($manifest === null || $state === null || (int) $state['enabled'] !== 0 || $state['version'] !== $manifest->version) {
            throw new RuntimeException('Disable an installed module with matching code/schema versions before preparing its update.');
        }
        // Also rejects built-in modules and namespace/path mismatches before any ZIP is staged.
        if (! str_starts_with($manifest->namespace, 'MCMS\\Modules\\')
            || realpath($manifest->path) !== realpath($this->root . '/installed/' . substr($manifest->namespace, strlen('MCMS\\Modules\\')))) {
            throw new RuntimeException('Only installed ZIP modules can be updated here.');
        }
        foreach ($this->modules->catalog() as $other) {
            foreach ($other->requires as $dependency) {
                if ((is_array($dependency) ? $dependency['id'] : $dependency) === $id
                    && (int) ($this->modules->states()[$other->id]['enabled'] ?? 0) === 1) {
                    throw new RuntimeException('Disable the dependent module first: ' . $other->id);
                }
            }
        }
        return $manifest;
    }

    private function assertNoPending(string $id): void
    {
        $state = $this->state($id);
        if ($state !== null && ! in_array($state['status'], ['applied', 'restored'], true)) {
            throw new RuntimeException('Finish or cancel the existing update first.');
        }
    }

    private function receipt(string $id, string $operation, array $statuses): array
    {
        $state = $this->state($id);
        if ($state === null || ! hash_equals($state['operation'], $operation) || ! in_array($state['status'], $statuses, true)) {
            throw new RuntimeException('Module update state changed. Reload the page.');
        }
        return $state;
    }

    private function directory(string $id): string
    {
        if (preg_match('/^[a-z][a-z0-9_-]*$/D', $id) !== 1) {
            throw new RuntimeException('Invalid module id.');
        }
        return $this->root . '/updates/' . $id;
    }

    private function installed(array $state): string
    {
        if (preg_match('/^[A-Z][A-Za-z0-9]*$/D', $state['folder']) !== 1) {
            throw new RuntimeException('Invalid module update namespace.');
        }
        return $this->root . '/installed/' . $state['folder'];
    }

    private function write(array $state): void
    {
        $directory = $this->directory($state['id']);
        $temp = tempnam($directory, 'state-');
        if ($temp === false || file_put_contents($temp, json_encode($state, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)) === false
            || ! rename($temp, $directory . '/state.json')) {
            throw new RuntimeException('Cannot persist module update receipt.');
        }
    }

    private function assertMigrationsPreserved(string $source, string $candidate): void
    {
        foreach ($this->phpFiles($source) as $file) {
            if (str_starts_with($file, 'Database/Migrations/')) {
                if (! is_file($candidate . '/' . $file)
                    || hash_file('sha256', $source . '/' . $file) !== hash_file('sha256', $candidate . '/' . $file)) {
                    throw new RuntimeException('Existing module migrations must be retained unchanged: ' . $file);
                }
            }
        }
    }

    private function phpFiles(string $directory): array
    {
        $paths = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                $paths[] = substr($file->getPathname(), strlen($directory) + 1);
            }
        }
        return $paths;
    }

    private function removeTree(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $ok = $file->isDir() && ! $file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            if (! $ok) {
                throw new RuntimeException('Cannot remove module update file.');
            }
        }
        if (! rmdir($directory)) {
            throw new RuntimeException('Cannot remove module update directory.');
        }
    }
}
