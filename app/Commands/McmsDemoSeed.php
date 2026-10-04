<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

final class McmsDemoSeed extends BaseCommand
{
    protected $group = 'mCMS';
    protected $name = 'mcms:demo:seed';
    protected $description = 'Add demo content once, only with MCMS_DEMO_SEED=1.';

    public function run(array $params): void
    {
        $status = service('mcmsDemoData')->seed();
        CLI::write('Demo data: ' . $status . '.', $status === 'completed' ? 'green' : 'yellow');
    }
}
