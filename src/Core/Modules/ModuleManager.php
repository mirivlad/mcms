<?php

declare(strict_types=1);

namespace MCMS\Core\Modules;

use CodeIgniter\Database\BaseConnection;
use Composer\Semver\Semver;
use CodeIgniter\Events\Events;
use CodeIgniter\Router\RouteCollection;
use Config\Services;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Navigation\NavigableResourceRegistry;
use MCMS\Core\Navigation\PublicPathUsageProviderInterface;
use MCMS\Core\Version;
use MCMS\Core\Events\CoreEvents;
use RuntimeException;
use Throwable;

final class ModuleManager
{
    private array $catalog = [];
    private bool $booted = false;
    private readonly NavigableResourceRegistry $navigation;

    public function __construct(
        private readonly BaseConnection $db,
        private readonly AdminRegistry $admin,
        private readonly ModuleServiceRegistry $services,
        private readonly string $modulesPath = ROOTPATH . 'modules',
        ?NavigableResourceRegistry $navigation = null,
        private readonly ?string $packagesPath = null,
        private readonly ?ModuleRuntime $runtime = null,
    ) {
        $this->navigation = $navigation ?? new NavigableResourceRegistry();
    }

    public function catalog(): array
    {
        if ($this->catalog !== []) {
            return $this->catalog;
        }

        $paths = glob($this->modulesPath . '/*/module.json') ?: [];
        if ($this->packagesPath !== null) {
            $paths = [...$paths, ...(glob($this->packagesPath . '/*/module.json') ?: [])];
        }
        foreach ($paths as $manifestPath) {
            $manifest = ModuleManifest::fromFile($manifestPath);
            if (isset($this->catalog[$manifest->id])) {
                throw new RuntimeException("Duplicate module id: {$manifest->id}");
            }
            foreach ($this->catalog as $existing) {
                if (strcasecmp($existing->namespace, $manifest->namespace) === 0) {
                    throw new RuntimeException("Duplicate module namespace: {$manifest->namespace}");
                }
            }

            // Composer can autoload module classes, but CodeIgniter's
            // FileLocator/MigrationRunner needs the module namespace registered
            // explicitly in order to discover migrations, views and other
            // non-class resources.
            service('autoloader')->addNamespace(
                $manifest->namespace,
                $manifest->path,
            );

            $this->catalog[$manifest->id] = $manifest;
        }

        ksort($this->catalog);

        return $this->catalog;
    }

    public function states(): array
    {
        if (! $this->registryAvailable()) {
            return [];
        }

        $rows = $this->db->table('mcms_modules')->get()->getResultArray();
        $states = [];

        foreach ($rows as $row) {
            $states[$row['module_id']] = $row;
        }

        return $states;
    }

    public function reservedPublicPaths(): array
    {
        $paths = [];
        foreach ($this->catalog() as $manifest) {
            $paths = [...$paths, ...$manifest->publicPaths];
        }

        return array_values(array_unique($paths));
    }

    public function installedDataProviders(string $contract): array
    {
        $states = $this->states();
        $providers = [];
        foreach ($this->catalog() as $manifest) {
            if (isset($states[$manifest->id], $manifest->dataProviders[$contract])) {
                $providers[$manifest->id] = $manifest->dataProviders[$contract];
            }
        }

        return $providers;
    }

    public function enable(string $id): void
    {
        if ($this->runtime !== null && ! $this->runtime->isExclusive()) {
            $this->runtime->exclusive(fn () => $this->enable($id));
            return;
        }
        $this->runtime?->assertCanEnable($id);
        $this->enableRecursive($id, []);
    }

    public function disable(string $id): void
    {
        if ($this->runtime !== null && ! $this->runtime->isExclusive()) {
            $this->runtime->exclusive(fn () => $this->disable($id));
            return;
        }
        $this->runtime?->assertAvailable();
        if (! $this->registryAvailable()) {
            return;
        }

        $states = $this->states();
        if ((int) ($states[$id]['enabled'] ?? 0) !== 1) {
            return;
        }

        foreach ($this->enabledManifests() as $candidate) {
            if ($candidate->id === $id) {
                continue;
            }

            foreach ($candidate->requires as $dependency) {
                $dependencyId = is_array($dependency) ? ($dependency['id'] ?? '') : $dependency;
                if ($dependencyId === $id) {
                    throw new RuntimeException(
                        "Cannot disable {$id}: enabled module {$candidate->id} depends on it."
                    );
                }
            }
        }

        $manifest = $this->catalog()[$id] ?? null;

        $this->db->table('mcms_modules')
            ->where('module_id', $id)
            ->update([
                'enabled'    => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        if ($manifest instanceof ModuleManifest) {
            Events::trigger(CoreEvents::MODULE_DISABLED, $manifest);
        }
    }

    public function updateEnabled(): void
    {
        if ($this->runtime !== null && ! $this->runtime->isExclusive()) {
            $this->runtime->exclusive(fn () => $this->updateEnabled());
            return;
        }
        $this->runtime?->assertAvailable();
        foreach ($this->enabledManifests() as $manifest) {
            Services::migrations()
                ->setNamespace($manifest->namespace)
                ->latest();

            $this->registerPermissions($manifest, service('settings'));

            $this->db->table('mcms_modules')
                ->where('module_id', $manifest->id)
                ->update([
                    'version'    => $manifest->version,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }
    }

    public function bootEnabled(): void
    {
        if ($this->runtime?->maintenance() !== null || $this->booted) {
            return;
        }

        $this->booted = true;

        foreach ($this->enabledManifests() as $manifest) {
            $this->provider($manifest)->boot(
                new ModuleContext(
                    $manifest, $this->admin, $this->services, $this->navigation,
                    \Config\Services::mcmsModuleStorage($manifest->id),
                )
            );
        }
    }

    public function registerRoutes(RouteCollection $routes): void
    {
        if ($this->runtime?->maintenance() !== null) {
            return;
        }
        foreach ($this->enabledManifests() as $manifest) {
            $this->provider($manifest)->routes($routes);
        }
    }

    private function enableRecursive(string $id, array $stack): void
    {
        $this->runtime?->assertCanEnable($id);
        $catalog = $this->catalog();
        $manifest = $catalog[$id] ?? null;

        if (! $manifest instanceof ModuleManifest) {
            throw new RuntimeException("Unknown module: {$id}");
        }

        if (! Semver::satisfies(Version::VERSION, $manifest->mcms)) {
            throw new RuntimeException(
                "Module {$id} requires mCMS {$manifest->mcms}; installed Core is " . Version::VERSION . '.'
            );
        }

        if (in_array($id, $stack, true)) {
            throw new RuntimeException('Circular module dependency: ' . implode(' -> ', [...$stack, $id]));
        }

        // Explicit enablement may load trusted package code, but an invalid
        // provider must fail before migrations or the enabled registry flag.
        if (! class_exists($manifest->provider)) {
            throw new RuntimeException("Module provider not found: {$manifest->provider}");
        }
        $provider = new \ReflectionClass($manifest->provider);
        if (! $provider->isSubclassOf(ModuleProviderInterface::class) || ! $provider->isInstantiable()
            || ($provider->getConstructor()?->getNumberOfRequiredParameters() ?? 0) !== 0) {
            throw new RuntimeException('Module provider must implement ' . ModuleProviderInterface::class . ' and be constructible without arguments.');
        }

        $states = $this->states();
        foreach ($this->installedDataProviders('core.public_paths') as $owner => $class) {
            if ($owner !== $id && (int) ($states[$owner]['enabled'] ?? 0) !== 1) {
                continue;
            }
            $provider = new $class();
            if (! $provider instanceof PublicPathUsageProviderInterface) {
                throw new RuntimeException("Invalid public-path usage provider: {$owner}");
            }
            $paths = $owner === $id ? $this->reservedPublicPaths() : $manifest->publicPaths;
            $conflicts = $provider->conflicts($paths);
            if ($conflicts !== []) {
                throw new RuntimeException(sprintf(lang('MCMS.errors.publicPathConflict'), $id, implode(', ', $conflicts), $owner));
            }
        }

        foreach ($manifest->requires as $dependency) {
            $dependencyId = is_array($dependency) ? ($dependency['id'] ?? '') : $dependency;
            if (! is_string($dependencyId) || $dependencyId === '') {
                throw new RuntimeException("Invalid dependency in module {$id}");
            }

            $dependencyManifest = $catalog[$dependencyId] ?? null;
            if (! $dependencyManifest instanceof ModuleManifest) {
                throw new RuntimeException("Module {$id} requires missing module {$dependencyId}.");
            }

            $constraint = is_array($dependency) ? ($dependency['version'] ?? '*') : '*';
            if (! is_string($constraint) || trim($constraint) === '') {
                throw new RuntimeException("Module {$id} has an invalid version constraint for {$dependencyId}.");
            }

            if (! Semver::satisfies($dependencyManifest->version, $constraint)) {
                throw new RuntimeException(
                    "Module {$id} requires {$dependencyId} {$constraint}; available version is {$dependencyManifest->version}."
                );
            }

            $this->enableRecursive($dependencyId, [...$stack, $id]);
        }

        $state = $this->states()[$id] ?? null;

        // Migrations are scoped to this module namespace. Core startup never
        // runs --all, so disabled/uninstalled modules cannot migrate themselves.
        Services::migrations()
            ->setNamespace($manifest->namespace)
            ->latest();

        $settings = service('settings');
        $this->registerPermissions($manifest, $settings);

        if ($state === null) {
            foreach ($manifest->settings as $key => $value) {
                $settings->set($manifest->id . '.' . $key, $value);
            }

            $now = date('Y-m-d H:i:s');
            $this->db->table('mcms_modules')->insert([
                'module_id'    => $manifest->id,
                'version'      => $manifest->version,
                'enabled'      => 1,
                'installed_at' => $now,
                'updated_at'   => $now,
            ]);

            Events::trigger(CoreEvents::MODULE_ENABLED, $manifest);

            return;
        }

        $this->db->table('mcms_modules')
            ->where('module_id', $id)
            ->update([
                'version'    => $manifest->version,
                'enabled'    => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        if ((int) ($state['enabled'] ?? 0) !== 1) {
            Events::trigger(CoreEvents::MODULE_ENABLED, $manifest);
        }
    }

    /** Only the fresh CLI update worker may migrate a disabled package. */
    public function migrateDisabled(string $id): void
    {
        if ($this->runtime === null) {
            throw new RuntimeException('Module update runtime is required.');
        }
        $this->runtime->assertWorker($id);
        $this->catalog = [];
        $manifest = $this->catalog()[$id] ?? null;
        $state = $this->states()[$id] ?? null;
        if ($manifest === null || $state === null || (int) $state['enabled'] !== 0) {
            throw new RuntimeException('An installed, disabled module is required.');
        }
        $provider = new \ReflectionClass($manifest->provider);
        if (! $provider->isSubclassOf(ModuleProviderInterface::class) || ! $provider->isInstantiable()
            || ($provider->getConstructor()?->getNumberOfRequiredParameters() ?? 0) !== 0) {
            throw new RuntimeException('Invalid module provider.');
        }
        $runner = Services::migrations()->setNamespace($manifest->namespace)->setSilent(false);
        if (! $runner->latest()) {
            throw new RuntimeException('Module migrations failed.');
        }
        // CI can report success when its migration lock was unavailable.
        $history = array_map(fn ($row) => $runner->getObjectUid($row), $runner->getHistory());
        foreach ($runner->findMigrations() as $migration) {
            if (! in_array($runner->getObjectUid($migration), $history, true)) {
                throw new RuntimeException('Module migration was not recorded.');
            }
        }
        $this->registerPermissions($manifest, service('settings'));
        $this->db->table('mcms_modules')->where('module_id', $id)->update([
            'version' => $manifest->version, 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function migrationFingerprint(string $namespace): string
    {
        $table = config('Migrations')->table;
        $rows = $this->db->tableExists($table)
            ? $this->db->table($table)->where('namespace', $namespace)->orderBy('id')->get()->getResultArray()
            : [];
        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    private function registerPermissions(ModuleManifest $manifest, object $settings): void
    {
        if ($manifest->permissions === []) {
            return;
        }

        $permissions = (array) $settings->get('AuthGroups.permissions');
        $settings->set('AuthGroups.permissions', array_replace($permissions, $manifest->permissions));

        $groups = (array) $settings->get('AuthGroups.groups');
        $matrix = (array) $settings->get('AuthGroups.matrix');
        $matrix['superadmin'] = array_values(array_unique([
            ...($matrix['superadmin'] ?? []),
            ...array_keys($manifest->permissions),
        ]));

        foreach ($manifest->grants as $group => $grants) {
            if (! array_key_exists($group, $groups)) {
                throw new RuntimeException(
                    "Module {$manifest->id} grants permissions to unknown group {$group}."
                );
            }

            $matrix[$group] = array_values(array_unique([
                ...($matrix[$group] ?? []),
                ...$grants,
            ]));
        }

        $settings->set('AuthGroups.matrix', $matrix);
    }

    private function enabledManifests(): array
    {
        if (! $this->registryAvailable()) {
            return [];
        }

        $states = $this->states();
        $catalog = $this->catalog();
        $enabled = [];

        foreach ($states as $id => $state) {
            if ((int) ($state['enabled'] ?? 0) === 1 && ! isset($catalog[$id])) {
                throw new RuntimeException(
                    "Enabled module {$id} is missing from the module directory."
                );
            }
        }

        foreach ($catalog as $id => $manifest) {
            if ((int) ($states[$id]['enabled'] ?? 0) === 1) {
                $enabled[$id] = $manifest;
            }
        }

        $ordered = [];
        $visiting = [];
        $visited = [];

        $visit = function (string $id) use (
            &$visit,
            &$ordered,
            &$visiting,
            &$visited,
            $enabled,
            $catalog
        ): void {
            if (isset($visited[$id])) {
                return;
            }

            if (isset($visiting[$id])) {
                throw new RuntimeException(
                    'Circular enabled-module dependency detected at ' . $id . '.'
                );
            }

            $manifest = $enabled[$id] ?? null;
            if (! $manifest instanceof ModuleManifest) {
                throw new RuntimeException("Enabled module manifest not found: {$id}");
            }

            if (! Semver::satisfies(Version::VERSION, $manifest->mcms)) {
                throw new RuntimeException(
                    "Enabled module {$id} requires mCMS {$manifest->mcms}; installed Core is "
                    . Version::VERSION . '.'
                );
            }

            $visiting[$id] = true;

            foreach ($manifest->requires as $dependency) {
                $dependencyId = is_array($dependency) ? ($dependency['id'] ?? '') : $dependency;
                $constraint = is_array($dependency) ? ($dependency['version'] ?? '*') : '*';

                if (! is_string($dependencyId) || $dependencyId === '') {
                    throw new RuntimeException("Invalid dependency in enabled module {$id}.");
                }

                if (! is_string($constraint) || trim($constraint) === '') {
                    throw new RuntimeException(
                        "Enabled module {$id} has an invalid constraint for {$dependencyId}."
                    );
                }

                $dependencyManifest = $catalog[$dependencyId] ?? null;
                if (! $dependencyManifest instanceof ModuleManifest) {
                    throw new RuntimeException(
                        "Enabled module {$id} requires missing module {$dependencyId}."
                    );
                }

                if (! isset($enabled[$dependencyId])) {
                    throw new RuntimeException(
                        "Enabled module {$id} requires disabled module {$dependencyId}."
                    );
                }

                if (! Semver::satisfies($dependencyManifest->version, $constraint)) {
                    throw new RuntimeException(
                        "Enabled module {$id} requires {$dependencyId} {$constraint}; available version is "
                        . $dependencyManifest->version . '.'
                    );
                }

                $visit($dependencyId);
            }

            unset($visiting[$id]);
            $visited[$id] = true;
            $ordered[] = $manifest;
        };

        foreach (array_keys($enabled) as $id) {
            $visit($id);
        }

        return $ordered;
    }

    private function provider(ModuleManifest $manifest): ModuleProviderInterface
    {
        if (! class_exists($manifest->provider)) {
            throw new RuntimeException("Module provider not found: {$manifest->provider}");
        }

        $provider = new $manifest->provider();

        if (! $provider instanceof ModuleProviderInterface) {
            throw new RuntimeException("Module provider must implement " . ModuleProviderInterface::class);
        }

        return $provider;
    }

    private function registryAvailable(): bool
    {
        try {
            return $this->db->tableExists('mcms_modules');
        } catch (Throwable) {
            return false;
        }
    }
}
