<?php

declare(strict_types=1);

namespace MCMS\Modules\Notifications\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

final class SendNotifications extends BaseCommand
{
    protected $group = 'mCMS';
    protected $name = 'notifications:send';
    protected $description = 'Send up to 100 pending order/comment notifications.';

    public function run(array $params)
    {
        service('mcmsModules')->bootEnabled();
        $services = service('mcmsModuleServices');
        if (! $services->has('notifications.mail')) {
            CLI::error('Enable Notifications first.');
            return EXIT_ERROR;
        }
        $result = $services->get('notifications.mail')->sendPending(null, 100);
        CLI::write('Sent: ' . $result['sent'] . '; failed: ' . $result['failed']);
        return $result['failed'] > 0 ? EXIT_ERROR : EXIT_SUCCESS;
    }
}
