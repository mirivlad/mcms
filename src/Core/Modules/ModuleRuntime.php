<?php

declare(strict_types=1);

namespace MCMS\Core\Modules;

use RuntimeException;

/** A request holds a read lock until shutdown; maintenance drains those readers. */
final class ModuleRuntime
{
    private $handle = null;
    private ?int $mode = null;
    private ?array $worker = null;

    public function __construct(private readonly string $root)
    {
    }

    public function readLock(): void
    {
        if ($this->mode !== null) {
            return;
        }
        if (! flock($this->handle(), LOCK_SH)) {
            throw new RuntimeException('Cannot lock module runtime.');
        }
        $this->mode = LOCK_SH;
    }

    public function exclusive(callable $operation): mixed
    {
        if ($this->isExclusive()) {
            return $operation();
        }
        $previous = $this->mode;
        if (! flock($this->handle(), LOCK_EX)) {
            throw new RuntimeException('Cannot lock module update.');
        }
        $this->mode = LOCK_EX;
        try {
            return $operation();
        } finally {
            flock($this->handle, $previous ?? LOCK_UN);
            $this->mode = $previous;
        }
    }

    public function isExclusive(): bool
    {
        return $this->mode === LOCK_EX;
    }

    public function release(): void
    {
        if ($this->handle !== null) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
            $this->handle = null;
            $this->mode = null;
        }
    }

    public function maintenance(): ?array
    {
        $path = $this->root . '/maintenance.json';
        if (! is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : ['id' => '', 'operation' => '', 'action' => 'invalid'];
    }

    public function beginMaintenance(string $id, string $operation, string $action): void
    {
        if (! $this->isExclusive()) {
            throw new RuntimeException('Maintenance requires the runtime lock.');
        }
        $this->assertAvailable();
        $path = $this->root . '/maintenance.json';
        $temp = tempnam($this->root, 'maintenance-');
        if ($temp === false || file_put_contents($temp, json_encode(compact('id', 'operation', 'action'), JSON_THROW_ON_ERROR)) === false
            || ! rename($temp, $path)) {
            throw new RuntimeException('Cannot persist module maintenance.');
        }
    }

    public function finishMaintenance(string $id, string $operation): void
    {
        $current = $this->maintenance();
        if (! $this->isExclusive() || ($current['id'] ?? null) !== $id || ($current['operation'] ?? null) !== $operation) {
            throw new RuntimeException('Module maintenance owner changed.');
        }
        if (! unlink($this->root . '/maintenance.json')) {
            throw new RuntimeException('Cannot finish module maintenance.');
        }
    }

    public function assertAvailable(): void
    {
        if ($this->maintenance() !== null) {
            throw new RuntimeException('Module update recovery is in progress. Open Modules to finish it.');
        }
    }

    public function assertCanEnable(string $id): void
    {
        $this->assertAvailable();
        if (preg_match('/^[a-z][a-z0-9_-]*$/D', $id) !== 1) {
            throw new RuntimeException('Invalid module id.');
        }
        $path = $this->root . '/updates/' . $id . '/state.json';
        if (is_file($path)) {
            $state = json_decode((string) file_get_contents($path), true);
            if (! is_array($state) || ! in_array($state['status'] ?? '', ['applied', 'restored'], true)) {
                throw new RuntimeException('Finish or cancel the prepared update before enabling this module.');
            }
        }
    }

    public function asWorker(string $id, string $operation, callable $work): mixed
    {
        $current = $this->maintenance();
        if (! $this->isExclusive() || ($current['id'] ?? null) !== $id || ($current['operation'] ?? null) !== $operation) {
            throw new RuntimeException('Update worker does not own maintenance.');
        }
        $this->worker = [$id, $operation];
        try {
            return $work();
        } finally {
            $this->worker = null;
        }
    }

    public function assertWorker(string $id): void
    {
        if (! $this->isExclusive() || ($this->worker[0] ?? null) !== $id) {
            throw new RuntimeException('Module migrations must run in the authorized update worker.');
        }
    }

    private function handle()
    {
        if ($this->handle === null) {
            if (! is_dir($this->root) && ! mkdir($this->root, 0755, true) && ! is_dir($this->root)) {
                throw new RuntimeException('Cannot create module runtime directory.');
            }
            $this->handle = fopen($this->root . '/runtime.lock', 'c');
            if ($this->handle === false) {
                $this->handle = null;
                throw new RuntimeException('Cannot open module runtime lock.');
            }
        }
        return $this->handle;
    }
}
