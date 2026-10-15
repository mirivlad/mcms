<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use MCMS\Core\Packages\CatalogValidator;

final class CatalogValidate extends BaseCommand
{
    protected $group = 'mCMS';
    protected $name = 'mcms:catalog:validate';
    protected $description = 'Validate catalog metadata, screenshots and ZIP bytes locally.';
    protected $options = ['--build' => 'Regenerate index.json after validating every package.'];

    public function run(array $params)
    {
        try {
            $count = CatalogValidator::validate(rtrim(ROOTPATH, '/'), CLI::getOption('build') !== null);
            CLI::write('Catalog valid: ' . $count . ' extensions.', 'green');
        } catch (\Throwable $e) {
            CLI::error($e->getMessage());
            exit(EXIT_ERROR);
        }
    }
}
