<?php

declare(strict_types=1);

namespace MCMS\Modules\Delivery;

use CodeIgniter\Router\RouteCollection;
use MCMS\Core\Modules\ModuleContext;
use MCMS\Core\Modules\ModuleProviderInterface;
use MCMS\Modules\Delivery\Services\Delivery;

final class Module implements ModuleProviderInterface
{
    public function boot(ModuleContext $context): void
    {
        $context->services->register('delivery.methods', new Delivery(db_connect()));
        $context->admin->addMenu(
            'delivery',
            '@Delivery.title',
            '/admin/delivery',
            'ti ti-truck-delivery',
            'delivery.view',
            68,
            group: 'commerce'
        );
        $context->admin->addSettings('delivery', '@Delivery.title', '/admin/delivery', 'ti ti-truck-delivery', 'delivery.view', 'commerce');
    }

    public function routes(RouteCollection $routes): void
    {
        foreach (['admin/delivery', 'admin/settings/delivery'] as $prefix) {
            $routes->group($prefix, ['filter' => 'session'], static function (RouteCollection $routes): void {
                $routes->get('/', '\\MCMS\\Modules\\Delivery\\Controllers\\Admin::index');
                $routes->get('create', '\\MCMS\\Modules\\Delivery\\Controllers\\Admin::form');
                $routes->post('create', '\\MCMS\\Modules\\Delivery\\Controllers\\Admin::save');
                $routes->get('(:num)', '\\MCMS\\Modules\\Delivery\\Controllers\\Admin::form/$1');
                $routes->post('(:num)', '\\MCMS\\Modules\\Delivery\\Controllers\\Admin::save/$1');
                $routes->post('(:num)/delete', '\\MCMS\\Modules\\Delivery\\Controllers\\Admin::delete/$1');
                $routes->post('order', '\\MCMS\\Modules\\Delivery\\Controllers\\Admin::order');
        });
        }
    }
}
