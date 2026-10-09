<?php

declare(strict_types=1);

namespace MCMS\Modules\Api;

use CodeIgniter\Router\RouteCollection;
use MCMS\Core\Modules\ModuleContext;
use MCMS\Core\Modules\ModuleProviderInterface;
use MCMS\Modules\Api\Services\Exchange;
use MCMS\Modules\Api\Services\Keys;

final class Module implements ModuleProviderInterface
{
    public function boot(ModuleContext $context): void
    {
        $keys = new Keys(db_connect());
        $context->services->register('api.keys', $keys);
        $context->services->register('api.exchange', new Exchange(db_connect(), $context->services, $keys));
        $context->admin->addMenu('api', '@Api.title', '/admin/api', 'ti ti-plug-connected', 'api.manage', 85, group: 'administration');
        $context->admin->addSettings('api', '@Api.title', '/admin/api', 'ti ti-plug-connected', 'api.manage', 'integrations');
    }

    public function routes(RouteCollection $routes): void
    {
        $routes->group('api/v1', static function (RouteCollection $routes): void {
            $controller = '\\MCMS\\Modules\\Api\\Controllers\\Exchange::';
            $routes->get('stock', $controller . 'stock');
            $routes->get('stock/(:num)', $controller . 'stockOne/$1');
            $routes->put('stock/(:num)', $controller . 'updateStock/$1');
            $routes->get('orders', $controller . 'orders');
            $routes->get('orders/(:num)', $controller . 'order/$1');
            $routes->get('delivery-methods', $controller . 'delivery');
        });
        foreach (['admin/api', 'admin/settings/api'] as $prefix) {
            $routes->group($prefix, ['filter' => 'session'], static function (RouteCollection $routes): void {
                $routes->get('/', '\\MCMS\\Modules\\Api\\Controllers\\Admin::index');
                $routes->post('keys', '\\MCMS\\Modules\\Api\\Controllers\\Admin::create');
                $routes->post('keys/(:num)/revoke', '\\MCMS\\Modules\\Api\\Controllers\\Admin::revoke/$1');
        });
        }
    }
}
