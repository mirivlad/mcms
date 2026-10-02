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
    ) {
        $this->navigation = $navigation ?? new NavigableResourceRegistry();
    }

    public function catalog(): array
    {
        if ($this->catalog !== []) {
            return $this->catalog;
        }

        foreach (glob($this->modulesPath . '/*/module.json') ?: [] as $manifestPath) {
            $manifest = ModuleManifest::fromFile($manifestPath);
            if (isset($this->catalog[$manifest->id])) {
                throw new RuntimeException("Duplicate module id: {$manifest->id}");
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
        $this->enableRecursive($id, []);
    }

    public function disable(string $id): void
    {
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
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        foreach ($this->enabledManifests() as $manifest) {
            $this->provider($manifest)->boot(
                new ModuleContext($manifest, $this->admin, $this->services, $this->navigation)
            );
        }
    }

    public function registerRoutes(RouteCollection $routes): void
    {
        foreach ($this->enabledManifests() as $manifest) {
            $this->provider($manifest)->routes($routes);
        }
    }

    private function enableRecursive(string $id, array $stack): void
    {
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
