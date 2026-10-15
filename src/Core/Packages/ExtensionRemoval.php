<?php

declare(strict_types=1);

namespace MCMS\Core\Packages;

use CodeIgniter\Database\BaseConnection;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModuleResources;
use MCMS\Core\Modules\ModuleRuntime;
use MCMS\Core\Themes\ThemeManager;
use RuntimeException;

/** Removal is journalled and resumes forward; no package PHP or down migrations run. */
final class ExtensionRemoval
{
    public function __construct(
        private readonly BaseConnection $db,
        private readonly ModuleManager $modules,
        private readonly ThemeManager $themes,
        private readonly ModuleRuntime $runtime,
        private readonly string $moduleRoot,
        private readonly string $themeRoot,
    ) {
    }

    public function retained(string $type, string $id): ?array
    {
        PackageState::id($id);
        return PackageState::read($this->root($type) . '/retained/' . $id . '.json');
    }

    public function preview(string $type, string $id): array
    {
        return $this->runtime->exclusive(function () use ($type, $id): array {
            $this->runtime->assertAvailable();
            if (is_file(WRITEPATH . 'backups/restore-active.json')) {
                throw new RuntimeException('Site restoration is in progress.');
            }
            PackageState::id($id);
            $root = $this->root($type);
            $this->modules->refresh();
            $this->themes->refresh();
            $manifest = $type === 'module' ? ($this->modules->catalog()[$id] ?? null) : $this->themes->find($id);
            if ($manifest === null || is_link($manifest->path)
                || realpath(dirname($manifest->path)) !== realpath($root . '/installed')) {
                throw new RuntimeException('Only installed external packages can be removed. Built-in packages are protected.');
            }
            $blockers = [];
            if ($type === 'module') {
                foreach ($this->modules->catalog() as $other) {
                    foreach ($other->requires as $dependency) {
                        if ($other->id !== $id && (is_array($dependency) ? $dependency['id'] : $dependency) === $id) {
                            $blockers[] = $other->id;
                        }
                    }
                }
                $this->runtime->assertCanEnable($id); // also rejects unresolved update receipts
            } elseif ($this->themes->activeId() === $id) {
                $blockers[] = lang('Extensions.activeTheme');
            }
            if (is_file($root . '/transactions/' . $id . '.json')) {
                throw new RuntimeException('Finish theme recovery before removal.');
            }
            foreach (glob($root . '/pending/*.zip') ?: [] as $archive) {
                if (filemtime($archive) < time() - 3600) {
                    continue;
                }
                try {
                    $pending = $type === 'module' ? \MCMS\Core\Modules\ModulePackages::inspectArchive($archive)
                        : \MCMS\Core\Themes\ThemePackages::inspectArchive($archive);
                    if ($pending['manifest']->id === $id) {
                        $blockers[] = lang('Extensions.pendingUpdate');
                    }
                } catch (\Throwable) {
                    // An invalid preview cannot be applied by either package service.
                }
            }
            $tables = [];
            $purgeError = null;
            if ($type === 'module') {
                try {
                    $tables = ModuleResources::assertCleanup($this->db, $manifest, $root);
                } catch (\Throwable $e) {
                    $purgeError = $e->getMessage();
                }
            }
            $resources = [
                'code' => $manifest->path, 'tables' => $tables,
                'settings' => $type === 'module' ? $id . '.*' : 'Core.theme{Appearance,Home,MenuAssignments,ExtraMenus}[' . $id . ']',
                'files' => $root . '/data/' . $id,
                'history' => $type === 'module' ? 'Module migrations and update snapshots' : 'Theme rollback snapshots',
            ];
            $fingerprint = hash('sha256', json_encode([
                $resources, $manifest->version, \MCMS\Core\Modules\ModulePackages::fingerprint($manifest->path),
                $type === 'module' ? $this->modules->migrationFingerprint($manifest->namespace) : null,
            ], JSON_THROW_ON_ERROR));
            return compact('type', 'id', 'manifest', 'blockers', 'purgeError', 'resources', 'fingerprint');
        });
    }

    public function remove(string $type, string $id, bool $purge, string $fingerprint, bool $backupConfirmed = false): void
    {
        $this->runtime->exclusive(function () use ($type, $id, $purge, $fingerprint, $backupConfirmed): void {
            $preview = $this->preview($type, $id); // all dependencies, including disabled/code-only, rechecked under writer lock
            if ($preview['blockers'] !== []) {
                throw new RuntimeException(lang('Extensions.blocked') . ' ' . implode(', ', $preview['blockers']));
            }
            if (! hash_equals($preview['fingerprint'], $fingerprint)) {
                throw new RuntimeException('Removal preview changed. Review it again.');
            }
            if ($purge && (! $backupConfirmed || $preview['purgeError'] !== null)) {
                throw new RuntimeException($preview['purgeError'] ?? lang('Extensions.backupRequired'));
            }
            $manifest = $preview['manifest'];
            $root = $this->root($type);
            $operation = bin2hex(random_bytes(16));
            $state = [
                'type' => $type, 'id' => $id, 'operation' => $operation, 'purge' => $purge,
                'folder' => basename($manifest->path), 'resources' => $preview['resources'],
                'retained' => ['version' => $manifest->version, 'namespace' => $manifest->namespace,
                    'source' => \MCMS\Core\Modules\ModulePackages::fingerprint($manifest->path)],
            ];
            if ($type === 'module') {
                $state['retained'] += [
                    'provider' => $manifest->provider, 'schema_version' => \Composer\Semver\Comparator::greaterThan(
                        $this->modules->states()[$id]['version'] ?? $manifest->version, $manifest->version)
                        ? $this->modules->states()[$id]['version'] : $manifest->version,
                    'registry_version' => $this->modules->states()[$id]['version'] ?? null,
                    'history' => $this->modules->migrationFingerprint($manifest->namespace), 'migrations' => [],
                ];
                foreach (glob($manifest->path . '/Database/Migrations/*.php') ?: [] as $file) {
                    $state['retained']['migrations']['Database/Migrations/' . basename($file)] = hash_file('sha256', $file);
                }
            }
            // Receipt precedes every DB/filesystem mutation. Startup resumes it before module boot.
            PackageState::write($root . '/removals/' . $id . '.json', $state);
            $this->resume($state);
        });
    }

    public function recoveryType(): string
    {
        foreach (['module', 'theme'] as $type) {
            foreach (glob($this->root($type) . '/removals/*.json') ?: [] as $file) {
                $state = PackageState::read($file);
                if (($state['operation'] ?? null) === ($this->runtime->maintenance()['operation'] ?? null)) {
                    return $type;
                }
            }
        }
        return 'module';
    }

    public function recover(): void
    {
        if ((glob($this->moduleRoot . '/removals/*.json') ?: []) === []
            && (glob($this->themeRoot . '/removals/*.json') ?: []) === []
            && ($this->runtime->maintenance()['action'] ?? '') !== 'remove') {
            return;
        }
        $this->runtime->exclusive(function (): void {
            foreach ([$this->moduleRoot, $this->themeRoot] as $root) {
                foreach (glob($root . '/removals/*.json') ?: [] as $file) {
                    $this->resume(PackageState::read($file));
                }
            }
            $flag = $this->runtime->maintenance();
            if (($flag['action'] ?? '') === 'remove') {
                $this->runtime->finishMaintenance($flag['id'], $flag['operation']);
            }
        });
    }

    private function resume(array $state): void
    {
        $id = $state['id'];
        PackageState::id($id);
        $root = $this->root($state['type']);
        if (preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/D', $state['folder']) !== 1) {
            throw new RuntimeException('Invalid removal journal.');
        }
        $flag = $this->runtime->maintenance();
        if ($flag === null) {
            $this->runtime->beginMaintenance($id, $state['operation'], 'remove');
        } elseif (($flag['action'] ?? '') !== 'remove' || $flag['id'] !== $id || $flag['operation'] !== $state['operation']) {
            throw new RuntimeException('Another operation owns maintenance.');
        }
        if ($state['type'] === 'module') {
            $this->db->table('mcms_modules')->where('module_id', $id)->update(['enabled' => 0]);
        }
        PackageState::write($root . '/retained/' . $id . '.json', $state['retained']);
        $code = $root . '/installed/' . $state['folder'];
        $trash = $root . '/removed-' . $state['operation'];
        if (is_dir($code)) {
            if (function_exists('opcache_invalidate')) {
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($code, \FilesystemIterator::SKIP_DOTS)) as $file) {
                    if ($file->getExtension() === 'php' && opcache_is_script_cached($file->getPathname())
                        && ! opcache_invalidate($file->getPathname(), true)) {
                        throw new RuntimeException('Restart PHP before retrying removal.');
                    }
                }
            }
            if (is_dir($trash) || ! rename($code, $trash)) {
                throw new RuntimeException('Cannot quarantine package code. Retry recovery.');
            }
        }
        if ($state['purge']) {
            $this->purge($state);
            PackageState::remove($root . '/retained/' . $id . '.json');
        }
        PackageState::remove($trash);
        $this->modules->refresh();
        $this->themes->refresh();
        if (method_exists(service('locator'), 'deleteCache')) {
            service('locator')->deleteCache();
        }
        // Removing the journal is the commit point. A leftover maintenance flag is cleared by recover().
        PackageState::remove($root . '/removals/' . $id . '.json');
        $this->runtime->finishMaintenance($id, $state['operation']);
    }

    private function purge(array $state): void
    {
        $id = $state['id'];
        $root = $this->root($state['type']);
        if ($state['type'] === 'module') {
            $forge = \Config\Database::forge($this->db);
            foreach ($state['resources']['tables'] as $table) {
                if (! $forge->dropTable($table, true)) {
                    throw new RuntimeException('Cannot drop owned table: ' . $table);
                }
            }
            $settingsTable = config('Settings')->database['table'];
            $rows = $this->db->table($settingsTable)->where('class', $id)->get()->getResultArray();
            foreach ($rows as $row) {
                service('settings')->forget($id . '.' . $row['key'], $row['context'] ?? null);
            }
            // Permission keys are strictly namespaced by ModuleManifest.
            $settings = service('settings');
            $permissions = (array) $settings->get('AuthGroups.permissions');
            $matrix = (array) $settings->get('AuthGroups.matrix');
            foreach (array_keys($permissions) as $key) {
                if (str_starts_with($key, $id . '.')) {
                    unset($permissions[$key]);
                }
            }
            foreach ($matrix as &$grants) {
                $grants = array_values(array_filter($grants, fn ($key) => ! str_starts_with($key, $id . '.')));
            }
            unset($grants);
            $settings->set('AuthGroups.permissions', $permissions);
            $settings->set('AuthGroups.matrix', $matrix);
            if ($this->db->tableExists('auth_permissions_users')) {
                $this->db->table('auth_permissions_users')->like('permission', $id . '.', 'after')->delete();
            }
            $this->db->table(config('Migrations')->table)->where('namespace', $state['retained']['namespace'])->delete();
            $this->db->table('mcms_modules')->where('module_id', $id)->delete();
            PackageState::remove($root . '/ownership/' . $id . '.json');
            PackageState::remove($root . '/updates/' . $id);
            foreach (glob($root . '/history/' . $id . '-*') ?: [] as $path) {
                if (preg_match('/^' . preg_quote($id, '/') . '-[a-f0-9]{24}$/D', basename($path))) {
                    PackageState::remove($path);
                }
            }
        } else {
            foreach (['themeAppearance', 'themeHome', 'themeMenuAssignments', 'themeExtraMenus'] as $key) {
                $all = (array) service('settings')->get('Core.' . $key);
                unset($all[$id]);
                service('settings')->set('Core.' . $key, $all);
            }
            PackageState::remove($root . '/history/' . $id);
            PackageState::remove($root . '/updates/' . $id . '.json');
        }
        PackageState::remove($root . '/data/' . $id);
    }

    private function root(string $type): string
    {
        return match ($type) {
            'module' => $this->moduleRoot, 'theme' => $this->themeRoot,
            default => throw new RuntimeException('Invalid extension type.'),
        };
    }
}
