<?php

declare(strict_types=1);

namespace MCMS\Core\Navigation;

use InvalidArgumentException;
use RuntimeException;

final class NavigableResourceRegistry
{
    /** @var array<string, NavigableResourceProviderInterface> */
    private array $providers = [];

    public function register(NavigableResourceProviderInterface $provider): void
    {
        $id = trim($provider->id());

        if (strlen($id) > 32 || ! preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', $id)) {
            throw new InvalidArgumentException(
                'Navigation provider ID must use lowercase ASCII letters, numbers, dot, dash or underscore.'
            );
        }

        if (isset($this->providers[$id])) {
            throw new RuntimeException("Navigation provider is already registered: {$id}");
        }

        $this->providers[$id] = $provider;
        ksort($this->providers);
    }

    public function has(string $id): bool
    {
        return isset($this->providers[$id]);
    }

    public function get(string $id): NavigableResourceProviderInterface
    {
        if (! isset($this->providers[$id])) {
            throw new RuntimeException("Navigation provider is not registered: {$id}");
        }

        return $this->providers[$id];
    }

    /**
     * @return array<string, NavigableResourceProviderInterface>
     */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * @return list<array{id:string,label:string}>
     */
    public function choices(): array
    {
        $choices = [];

        foreach ($this->providers as $id => $provider) {
            $choices[] = [
                'id' => $id,
                'label' => $provider->label(),
            ];
        }

        return $choices;
    }

    /**
     * @return array{id:string,label:string,url:string}|null
     */
    public function resolve(string $providerId, string $resourceId): ?array
    {
        if (! isset($this->providers[$providerId])) {
            return null;
        }

        return $this->providers[$providerId]->resolve($resourceId);
    }
}
