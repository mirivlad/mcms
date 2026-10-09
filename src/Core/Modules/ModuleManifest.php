<?php

declare(strict_types=1);

namespace MCMS\Core\Modules;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class ModuleManifest
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $version,
        public readonly string $namespace,
        public readonly string $provider,
        public readonly string $mcms,
        public readonly string $description = '',
        public readonly array $requires = [],
        public readonly array $settings = [],
        public readonly array $permissions = [],
        public readonly array $grants = [],
        public readonly string $path = '',
        public readonly array $publicPaths = [],
        public readonly array $dataProviders = [],
    ) {
    }

    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            throw new RuntimeException("Module manifest not found: {$path}");
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Invalid module manifest: {$path}", 0, $e);
        }

        if (! is_array($data)) {
            throw new InvalidArgumentException('Module manifest must be an object.');
        }

        return self::fromArray($data, dirname($path));
    }

    public static function fromArray(array $data, string $path = ''): self
    {
        foreach (['id', 'name', 'version', 'namespace', 'provider', 'mcms'] as $required) {
            if (! isset($data[$required]) || ! is_string($data[$required]) || trim($data[$required]) === '') {
                throw new InvalidArgumentException("Missing module manifest field: {$required}");
            }
        }

        if (! preg_match('/^[a-z][a-z0-9_-]*$/', $data['id'])) {
            throw new InvalidArgumentException('Module id must use lowercase ASCII letters, numbers, dash or underscore.');
        }

        $permissions = is_array($data['permissions'] ?? null) ? $data['permissions'] : [];
        foreach ($permissions as $permission => $description) {
            if (! is_string($permission)
                || ! str_starts_with($permission, $data['id'] . '.')
                || ! is_string($description)
                || trim($description) === ''
            ) {
                throw new InvalidArgumentException(
                    "Module permissions must use the {$data['id']}.* namespace and a non-empty description."
                );
            }
        }

        $grants = is_array($data['grants'] ?? null) ? $data['grants'] : [];
        foreach ($grants as $group => $grantedPermissions) {
            if (! is_string($group) || $group === '' || ! is_array($grantedPermissions)) {
                throw new InvalidArgumentException('Module grants must map a group name to a permission list.');
            }

            foreach ($grantedPermissions as $permission) {
                if (! is_string($permission) || ! array_key_exists($permission, $permissions)) {
                    throw new InvalidArgumentException(
                        "Module grant {$group} refers to an undeclared permission."
                    );
                }
            }
        }

        $publicPaths = $data['public_paths'] ?? [];
        if (! is_array($publicPaths) || ! array_is_list($publicPaths)) {
            throw new InvalidArgumentException('Module public_paths must be a list.');
        }
        foreach ($publicPaths as $publicPath) {
            if (! is_string($publicPath) || preg_match('/^[a-z0-9][a-z0-9._-]*$/', $publicPath) !== 1) {
                throw new InvalidArgumentException('Module public_paths must contain fixed top-level URL segments.');
            }
        }

        $dataProviders = $data['data_providers'] ?? [];
        if (! is_array($dataProviders)) {
            throw new InvalidArgumentException('Module data_providers must be a map.');
        }
        foreach ($dataProviders as $contract => $provider) {
            if (! is_string($contract) || preg_match('/^[a-z][a-z0-9._-]*$/', $contract) !== 1
                || ! is_string($provider) || ! str_starts_with($provider, trim($data['namespace'], '\\') . '\\')) {
                throw new InvalidArgumentException('Data providers must name a contract and a class in the owning module namespace.');
            }
        }

        return new self(
            id: $data['id'],
            name: $data['name'],
            version: $data['version'],
            namespace: trim($data['namespace'], '\\'),
            provider: $data['provider'],
            mcms: $data['mcms'],
            description: (string) ($data['description'] ?? ''),
            requires: is_array($data['requires'] ?? null) ? $data['requires'] : [],
            settings: is_array($data['settings'] ?? null) ? $data['settings'] : [],
            permissions: $permissions,
            grants: $grants,
            path: $path,
            publicPaths: array_values(array_unique($publicPaths)),
            dataProviders: $dataProviders,
        );
    }
}
