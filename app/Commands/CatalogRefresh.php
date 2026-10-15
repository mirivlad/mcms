<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

final class CatalogRefresh extends BaseCommand
{
    protected $group = 'mCMS';
    protected $name = 'mcms:catalog:refresh';
    protected $description = 'Refresh the GitHub extension catalog (at most daily by default).';
    protected $options = ['--force' => 'Check now, with a one-minute retry limit.'];

    public function run(array $params)
    {
        try {
            $cache = service('mcmsExtensionCatalog')->refresh(CLI::getOption('force') !== null);
            CLI::write('Last successful check: ' . (isset($cache['checked_at']) ? gmdate(DATE_ATOM, $cache['checked_at']) : 'never'));
        } catch (\Throwable $e) {
            CLI::error($e->getMessage());
            exit(EXIT_ERROR);
        }
    }
}
