<?php

declare(strict_types=1);

namespace MCMS\Modules\Stock;

use CodeIgniter\Router\RouteCollection;
use MCMS\Core\Modules\ModuleContext;
use MCMS\Core\Modules\ModuleProviderInterface;
use MCMS\Modules\Stock\Services\Stock;

final class Module implements ModuleProviderInterface
{
    public function boot(ModuleContext $context): void
    {
        $context->services->register(
            'stock.inventory',
            new Stock(db_connect(), $context->services->get('catalogue.repository'))
        );
        $context->admin->addMenu('stock', '@Stock.title', '/admin/stock', 'ti ti-packages', 'stock.view', 66, group: 'commerce');
    }

    public function routes(RouteCollection $routes): void
    {
        $routes->group('admin/stock', ['filter' => 'session'], static function (RouteCollection $routes): void {
            $routes->get('/', '\\MCMS\\Modules\\Stock\\Controllers\\Admin::index');
            $routes->post('variant/(:num)', '\\MCMS\\Modules\\Stock\\Controllers\\Admin::update/$1');
        });
        $routes->post('stock/variant/(:num)', '\\MCMS\\Modules\\Stock\\Controllers\\Quick::update/$1', ['filter' => 'session']);
    }
}
