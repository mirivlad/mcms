<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

final class McmsBackupRestore extends BaseCommand
{
    protected $group = 'mCMS';
    protected $name = 'mcms:backup:restore';
    protected $description = 'Internal worker for a validated and confirmed site restore.';

    public function run(array $params): void
    {
        if (count($params) !== 1 || preg_match('/^[a-f0-9]{32}$/D', $params[0]) !== 1) {
            CLI::error('Expected a prepared restore token.');
            exit(1);
        }
        try {
            service('mcmsBackupRestore')->run($params[0]);
            CLI::write('Restoration finished.', 'green');
        } catch (Throwable $e) {
            CLI::error('Restore failed: ' . $e->getMessage());
            exit(1);
        }
    }
}
