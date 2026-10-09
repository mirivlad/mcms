<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

final class ModuleUpdate extends BaseCommand
{
    protected $group = 'mCMS';
    protected $name = 'mcms:module:update';
    protected $description = 'Run a prepared module update or recovery in a fresh PHP process.';

    public function run(array $params): void
    {
        if (count($params) !== 3 || ! in_array($params[2], ['apply', 'restore'], true)) {
            CLI::error('Expected module id, operation token and apply/restore.');
            exit(1);
        }
        try {
            service('mcmsModuleUpdates')->runWorker(...$params);
            CLI::write('Module operation completed; the next web request finishes maintenance.', 'green');
        } catch (\Throwable $e) {
            CLI::error($e->getMessage());
            exit(1);
        }
    }
}
