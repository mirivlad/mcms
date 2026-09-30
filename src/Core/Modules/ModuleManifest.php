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
        public readonly string $description = '',
        public readonly array $requires = [],
        public readonly array $settings = [],
        public readonly array $permissions = [],
        public readonly array $grants = [],
        public readonly string $path = '',
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

        foreach (['id', 'name', 'version', 'namespace', 'provider'] as $required) {
            if (! isset($data[$required]) || ! is_string($data[$required]) || trim($data[$required]) === '') {
                throw new InvalidArgumentException("Missing module manifest field: {$required}");
            }
        }

        if (! preg_match('/^[a-z][a-z0-9_-]*$/', $data['id'])) {
            throw new InvalidArgumentException('Module id must use lowercase ASCII letters, numbers, dash or underscore.');
        }

        return new self(
            id: $data['id'],
            name: $data['name'],
            version: $data['version'],
            namespace: trim($data['namespace'], '\\'),
            provider: $data['provider'],
            description: (string) ($data['description'] ?? ''),
            requires: is_array($data['requires'] ?? null) ? $data['requires'] : [],
            settings: is_array($data['settings'] ?? null) ? $data['settings'] : [],
            permissions: is_array($data['permissions'] ?? null) ? $data['permissions'] : [],
            grants: is_array($data['grants'] ?? null) ? $data['grants'] : [],
            path: dirname($path),
        );
    }
}
