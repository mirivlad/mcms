<?php

declare(strict_types=1);

namespace MCMS\Core\Updates;

use MCMS\Core\Backup\BackupManager;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModuleUpdates;
use MCMS\Core\Packages\ExtensionCatalog;
use MCMS\Core\Themes\ThemeUpdates;
use RuntimeException;
use Throwable;

/** Unattended updates use the same verified ZIP, locks and recovery workflow as manual updates. */
final class AutoUpdater
{
    public function __construct(
        private readonly UpdatePolicy $policy,
        private readonly ExtensionCatalog $catalog,
        private readonly ModuleManager $modules,
        private readonly ModuleUpdates $moduleUpdates,
        private readonly ThemeUpdates $themeUpdates,
        private readonly BackupManager $backups,
    ) {}

    public function run(bool $force = false): array
    {
        if (! is_cli()) {
            throw new RuntimeException('Automatic updates run only from CLI.');
        }
        $root = $this->policy->root();
        if (! is_dir($root) && ! mkdir($root, 0700, true) && ! is_dir($root)) {
            throw new RuntimeException('Cannot create updater state directory.');
        }
        $lock = fopen($root . '/run.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('An update job is already running.');
        }
        $results = [];
        try {
            $settings = $this->policy->get();
            if (! $settings['modules'] && ! $settings['themes']) {
                return [];
            }
            if (is_file(WRITEPATH . 'backups/restore-active.json') || service('mcmsModuleRuntime')->maintenance() !== null) {
                throw new RuntimeException('Maintenance or site restore in progress.');
            }
            $this->catalog->refresh($force);
            foreach ($this->catalog->entries() as $entry) {
                if (! ($entry['update'] ?? false) || in_array($entry['id'], $settings['excluded'], true)) {
                    continue;
                }
                $type = $entry['type'];
                if (! ($settings[$type === 'theme' ? 'themes' : 'modules'] ?? false)) {
                    continue;
                }
                $id = $entry['id'];
                $version = $entry['available']['version'] ?? null;
                if (! is_string($version)) {
                    continue;
                }
                // A failed candidate must never be retried unattended every
                // six hours, creating repeated backups or repeating downtime.
                // A newly published version is a new candidate.
                if ($this->previouslySkipped($type, $id, $version)) {
                    continue;
                }
                try {
                    if ($type === 'theme') {
                        $this->updateTheme($id, $version);
                    } else {
                        $this->updateModule($id, $version);
                    }
                    $result = ['type' => $type, 'id' => $id, 'version' => $version, 'result' => 'updated'];
                } catch (Throwable $e) {
                    $result = ['type' => $type, 'id' => $id, 'version' => $version,
                        'result' => 'skipped', 'reason' => $e->getMessage()];
                }
                $this->policy->record($result);
                $results[] = $result;
                if (service('mcmsModuleRuntime')->maintenance() !== null) {
                    break;
                }
            }
            if ($results === []) {
                $this->policy->record(['result' => 'checked', 'reason' => 'No eligible extension updates']);
            }
            return $results;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function previouslySkipped(string $type, string $id, string $version): bool
    {
        foreach ($this->policy->status()['events'] ?? [] as $event) {
            if (($event['type'] ?? null) === $type
                && ($event['id'] ?? null) === $id
                && ($event['version'] ?? null) === $version) {
                return ($event['result'] ?? null) === 'skipped';
            }
        }
        return false;
    }

    private function updateTheme(string $id, string $version): void
    {
        $preview = $this->catalog->prepare($id, $version);
        if ($preview['type'] !== 'theme' || $preview['action'] !== 'update') {
            throw new RuntimeException('Unexpected package operation.');
        }
        try {
            $this->backups->create();
            $this->themeUpdates->apply($id, $preview['token'], $preview['sha256'], $preview['source']);
        } finally {
            service('mcmsThemePackages')->cancel($preview['token']);
        }
    }

    private function updateModule(string $id, string $version): void
    {
        $current = $this->modules->catalog()[$id] ?? null;
        $state = $this->modules->states()[$id] ?? null;
        if ($current === null || $state === null) {
            throw new RuntimeException('Module state is unavailable.');
        }
        // Coordinated dependent shutdown/restart requires its own recovery journal.
        foreach ($this->modules->catalog() as $other) {
            foreach ($other->requires as $dependency) {
                $dep = is_array($dependency) ? $dependency['id'] : $dependency;
                if ($dep === $id && (int) ($this->modules->states()[$other->id]['enabled'] ?? 0) === 1) {
                    throw new RuntimeException('Enabled dependent requires coordinated manual update: ' . $other->id);
                }
            }
        }
        $wasEnabled = (int) $state['enabled'] === 1;
        $queued = false;
        try {
            if ($wasEnabled) {
                $this->modules->disable($id);
            }
            $preview = $this->catalog->prepare($id, $version);
            if ($preview['type'] !== 'module' || $preview['action'] !== 'update') {
                throw new RuntimeException('Unexpected package operation.');
            }
            try {
                $receipt = $this->moduleUpdates->queue($id, $preview['token'], $preview['sha256']);
                $queued = true;
            } finally {
                service('mcmsModulePackages')->cancel($preview['token']);
            }
            if (self::containsNewMigrations($current->path, WRITEPATH . 'modules-data/updates/' . $id . '/candidate')) {
                throw new RuntimeException('New database migrations require manual approval.');
            }
            $this->backups->create();
            $this->moduleUpdates->begin($id, $receipt['operation'], 'apply');
            $this->moduleUpdates->launch($id, $receipt['operation'], 'apply');
            $queued = false;
            if ($wasEnabled) {
                $this->modules->refresh();
                $this->modules->enable($id);
            }
        } finally {
            // Failures before applying the candidate must not leave modules disabled.
            if (service('mcmsModuleRuntime')->maintenance() === null) {
                if ($queued) {
                    $pending = $this->moduleUpdates->state($id);
                    if (($pending['status'] ?? null) === 'queued') {
                        $this->moduleUpdates->cancel($id, $pending['operation']);
                    }
                }
                if ($wasEnabled && (int) ($this->modules->states()[$id]['enabled'] ?? 0) === 0) {
                    $this->modules->refresh();
                    $this->modules->enable($id);
                }
            }
        }
    }

    /** Reject added, deleted or changed migration scripts rather than attempting rollback of DDL. */
    public static function containsNewMigrations(string $old, string $candidate): bool
    {
        $scan = static function (string $root): array {
            $out = [];
            $dir = $root . '/Database/Migrations';
            if (! is_dir($dir)) {
                return [];
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                    $out[substr($file->getPathname(), strlen($dir) + 1)] = hash_file('sha256', $file->getPathname());
                }
            }
            return $out;
        };
        return $scan($old) !== $scan($candidate);
    }
}
