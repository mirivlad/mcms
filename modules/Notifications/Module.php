<?php

declare(strict_types=1);

namespace MCMS\Modules\Notifications;

use CodeIgniter\Events\Events;
use CodeIgniter\Router\RouteCollection;
use MCMS\Core\Modules\ModuleContext;
use MCMS\Core\Modules\ModuleProviderInterface;
use MCMS\Modules\Notifications\Services\Notifications;
use MCMS\Modules\Orders\Api\OrderEvents;

final class Module implements ModuleProviderInterface
{
    public function boot(ModuleContext $context): void
    {
        $notifications = new Notifications(db_connect(), $context->services->get('orders.orders'));
        $context->services->register('notifications.mail', $notifications);
        Events::on(OrderEvents::RECORDED, $notifications->record(...));
        Events::on(OrderEvents::COMMITTED, static fn (int $id): array => $notifications->sendPending($id, 1));
        $context->admin->addMenu('notifications', '@Notifications.title', '/admin/notifications', 'ti ti-mail', 'notifications.manage', 69, group: 'commerce');
        $context->admin->addSettings('notifications', '@Notifications.title', '/admin/notifications', 'ti ti-mail', 'notifications.manage', 'commerce');
    }

    public function routes(RouteCollection $routes): void
    {
        foreach (['admin/notifications', 'admin/settings/notifications'] as $prefix) {
            $routes->group($prefix, ['filter' => 'session'], static function (RouteCollection $routes): void {
                $controller = '\\MCMS\\Modules\\Notifications\\Controllers\\Admin::';
                $routes->get('/', $controller . 'index');
                $routes->post('/', $controller . 'save');
                $routes->post('test', $controller . 'test');
                $routes->post('retry', $controller . 'retry');
            });
        }
    }
}
