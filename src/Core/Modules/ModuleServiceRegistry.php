<?php

declare(strict_types=1);

namespace MCMS\Core\Modules;

use InvalidArgumentException;
use RuntimeException;

final class ModuleServiceRegistry
{
    private array $services = [];

    public function register(string $id, object $service): void
    {
        if (! preg_match('/^[a-z][a-z0-9._-]*$/', $id)) {
            throw new InvalidArgumentException(
                'Module service ID must use lowercase ASCII letters, numbers, dot, dash or underscore.'
            );
        }

        if (isset($this->services[$id])) {
            throw new RuntimeException("Module service is already registered: {$id}");
        }

        $this->services[$id] = $service;
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }

    public function get(string $id): object
    {
        if (! isset($this->services[$id])) {
            throw new RuntimeException("Module service is not registered: {$id}");
        }

        return $this->services[$id];
    }

    public function all(): array
    {
        return $this->services;
    }
}
