<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

final class AutomaticUpdates extends BaseCommand
{
    protected $group = 'mCMS';
    protected $name = 'mcms:updates:run';
    protected $description = 'Apply eligible unattended extension updates under the administrator policy.';
    protected $options = ['--force' => 'Refresh the extension catalog first (rate limited).'];

    public function run(array $params)
    {
        try {
            $results = service('mcmsAutoUpdater')->run(CLI::getOption('force') !== null);
            if ($results === []) {
                CLI::write('No eligible automatic extension updates.');
            }
            $errors = false;
            foreach ($results as $result) {
                $message = $result['type'] . '/' . $result['id'] . ' ' . $result['version'] . ': ' . $result['result'];
                if ($result['result'] !== 'updated') {
                    $errors = true;
                    $message .= ' (' . ($result['reason'] ?? 'unknown') . ')';
                }
                CLI::write($message);
            }
            if (! $errors && service('mcmsModuleRuntime')->maintenance() === null) {
                $core = service('mcmsCoreUpdater')->run();
                if ($core !== null) {
                    CLI::write('Core redeploy requested: ' . ($core['target'] ?? 'unknown'));
                }
            }
            if ($errors) {
                exit(EXIT_ERROR);
            }
        } catch (\Throwable $e) {
            CLI::error($e->getMessage());
            exit(EXIT_ERROR);
        }
    }
}
