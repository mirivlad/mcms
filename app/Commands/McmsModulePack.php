<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use MCMS\Core\Modules\ModulePackages;
use RuntimeException;

final class McmsModulePack extends BaseCommand
{
    protected $group = 'mCMS';
    protected $name = 'mcms:module:pack';
    protected $description = 'Build a ZIP package from a module directory (including a local repository checkout).';
    protected $usage = 'mcms:module:pack <directory> <output.zip>';

    public function run(array $params): void
    {
        if (count($params) !== 2) {
            throw new RuntimeException('Usage: php spark mcms:module:pack <directory> <output.zip>');
        }
        // Spark changes the process working directory to public/ on bootstrap.
        $source = str_starts_with($params[0], '/') ? $params[0] : ROOTPATH . $params[0];
        $output = str_starts_with($params[1], '/') ? $params[1] : ROOTPATH . $params[1];
        ModulePackages::pack($source, $output);
        CLI::write('Module package created: ' . $params[1], 'green');
    }
}
