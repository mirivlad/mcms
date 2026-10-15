<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;

final class McmsUpdate extends BaseCommand
{
    protected $group = 'mCMS';
    protected $name = 'mcms:update';
    protected $description = 'Apply Core/package migrations and update enabled modules.';

    public function run(array $params): void
    {
        $runtime = service('mcmsModuleRuntime');
        $runtime->exclusive(function () use ($runtime): void {
            try {
                service('mcmsExtensionRemoval')->recover();
            } catch (\Throwable $e) {
                CLI::error('Extension removal recovery pending: ' . $e->getMessage());
                return;
            }
            if ($runtime->maintenance() !== null) {
                CLI::write('Module recovery pending; startup migrations skipped.', 'yellow');
                return;
            }
            $this->update();
        });
    }

    private function update(): void
    {
        $migrations = Services::migrations();

        CLI::write('Updating mCMS Core...', 'yellow');
        $migrations->setNamespace('App')->latest();

        CLI::write('Updating CodeIgniter Settings...', 'yellow');
        $migrations->setNamespace('CodeIgniter\\Settings')->latest();

        CLI::write('Updating CodeIgniter Shield...', 'yellow');
        $migrations->setNamespace('CodeIgniter\\Shield')->latest();

        CLI::write('Synchronizing Core access definitions...', 'yellow');
        service('mcmsAccess')->syncCoreDefinitions();

        CLI::write('Updating enabled modules...', 'yellow');
        service('mcmsModules')->updateEnabled();

        CLI::write('mCMS update complete.', 'green');
    }
}
