<?php

declare(strict_types=1);

namespace MCMS\Modules\Orders;

use CodeIgniter\Router\RouteCollection;
use MCMS\Core\Modules\ModuleContext;
use MCMS\Core\Modules\ModuleProviderInterface;
use MCMS\Modules\Orders\Services\Orders;

final class Module implements ModuleProviderInterface
{
    public function boot(ModuleContext $context): void
    {
        $context->services->register(
            'orders.orders',
            new Orders(
                db_connect(),
                $context->services->get('cart.cart'),
                $context->services->get('stock.inventory'),
            )
        );
        $context->admin->addMenu(
            'orders',
            '@Orders.title',
            '/admin/orders',
            'ti ti-receipt',
            'orders.view',
            67,
            group: 'commerce'
        );
    }

    public function routes(RouteCollection $routes): void
    {
        $routes->post('order/checkout', '\\MCMS\\Modules\\Orders\\Controllers\\Checkout::create');

        $routes->group('admin/orders', ['filter' => 'session'], static function (RouteCollection $routes): void {
            $routes->get('/', '\\MCMS\\Modules\\Orders\\Controllers\\Admin::index');
            $routes->get('(:num)', '\\MCMS\\Modules\\Orders\\Controllers\\Admin::show/$1');
            $routes->post('(:num)/status', '\\MCMS\\Modules\\Orders\\Controllers\\Admin::status/$1');
        });
    }
}
