<?php

declare(strict_types=1);

namespace MCMS\Core\Updates;

use MCMS\Core\Packages\PackageState;
use RuntimeException;

/** Persistent policy shared by the web UI and the CLI worker. No secrets here. */
final class UpdatePolicy
{
    public function __construct(private readonly string $root) {}

    public function get(): array
    {
        $stored = PackageState::read($this->root . '/policy.json') ?? [];
        return [
            'core' => ($stored['core'] ?? false) === true,
            'modules' => ($stored['modules'] ?? false) === true,
            'themes' => ($stored['themes'] ?? false) === true,
            'excluded' => array_values(array_filter(is_array($stored['excluded'] ?? null) ? $stored['excluded'] : [], static fn ($id) =>
                is_string($id) && preg_match('/^[a-z][a-z0-9_-]*$/D', $id) === 1)),
        ];
    }

    public function save(array $input): array
    {
        foreach (['core', 'modules', 'themes'] as $key) {
            if (! array_key_exists($key, $input) || ! is_bool($input[$key])) {
                throw new RuntimeException('Invalid update policy flag: ' . $key);
            }
        }
        $excluded = $input['excluded'] ?? [];
        if (! is_array($excluded) || count($excluded) > 200) {
            throw new RuntimeException('Invalid extension exclusion list.');
        }
        foreach ($excluded as $id) {
            if (! is_string($id) || preg_match('/^[a-z][a-z0-9_-]*$/D', $id) !== 1) {
                throw new RuntimeException('Invalid excluded extension id.');
            }
        }
        $policy = [
            'core' => $input['core'], 'modules' => $input['modules'],
            'themes' => $input['themes'], 'excluded' => array_values(array_unique($excluded)),
        ];
        PackageState::write($this->root . '/policy.json', $policy);
        return $policy;
    }

    public function status(): array
    {
        return PackageState::read($this->root . '/status.json') ?? [];
    }

    public function record(array $event): void
    {
        $status = $this->status();
        $events = $status['events'] ?? [];
        array_unshift($events, ['at' => gmdate(DATE_ATOM), ...$event]);
        PackageState::write($this->root . '/status.json', [
            'last_run' => gmdate(DATE_ATOM),
            'events' => array_slice($events, 0, 80),
        ]);
    }

    public function root(): string { return $this->root; }
}
