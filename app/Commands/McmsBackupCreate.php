<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

final class McmsBackupCreate extends BaseCommand
{
    protected $group = 'mCMS';
    protected $name = 'mcms:backup:create';
    protected $description = 'Create a complete site ZIP backup (suitable for cron).';
    protected $usage = 'mcms:backup:create [--keep=N]';
    protected $options = [
        '--keep' => 'Retain only the N newest backups after a successful creation (optional).',
    ];

    public function run(array $params): void
    {
        try {
            $keep = CLI::getOption('keep');
            if ($keep !== null && $keep !== false
                && (! is_string($keep) || preg_match('/^[1-9][0-9]*$/D', $keep) !== 1)) {
                throw new \InvalidArgumentException('--keep must be a positive integer.');
            }
            $manager = service('mcmsBackups');
            $name = $manager->create();
            CLI::write('Backup created: ' . $name, 'green');
            if ($keep !== null && $keep !== false) {
                foreach (array_slice($manager->listing(), (int) $keep) as $old) {
                    $file = $manager->file($old['name']);
                    if ($file !== null && ! unlink($file)) {
                        throw new \RuntimeException('Cannot remove old backup: ' . $old['name']);
                    }
                }
                CLI::write('Retention applied: newest ' . $keep . ' backups.', 'green');
            }
        } catch (Throwable $e) {
            CLI::error('Backup failed: ' . $e->getMessage());
            exit(1);
        }
    }
}
