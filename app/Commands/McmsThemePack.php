<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use MCMS\Core\Themes\ThemePackages;
use RuntimeException;

final class McmsThemePack extends BaseCommand
{
    protected $group = 'mCMS';
    protected $name = 'mcms:theme:pack';
    protected $description = 'Build a ZIP package from a theme directory (including a local repository export).';
    protected $usage = 'mcms:theme:pack <directory> <output.zip>';

    public function run(array $params): void
    {
        if (count($params) !== 2) {
            throw new RuntimeException('Usage: php spark mcms:theme:pack <directory> <output.zip>');
        }
        // Spark changes the process working directory to public/ on bootstrap.
        $source = str_starts_with($params[0], '/') ? $params[0] : ROOTPATH . $params[0];
        $output = str_starts_with($params[1], '/') ? $params[1] : ROOTPATH . $params[1];
        ThemePackages::pack($source, $output);
        CLI::write('Theme package created: ' . $params[1], 'green');
    }
}
