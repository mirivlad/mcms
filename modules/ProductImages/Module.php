<?php

declare(strict_types=1);

namespace MCMS\Modules\ProductImages;

use CodeIgniter\Router\RouteCollection;
use MCMS\Core\Modules\ModuleContext;
use MCMS\Core\Modules\ModuleProviderInterface;
use MCMS\Modules\ProductImages\Services\ProductImages;

final class Module implements ModuleProviderInterface
{
    public function boot(ModuleContext $context): void
    {
        $context->services->register('product-images.library', new ProductImages(db_connect(), WRITEPATH . 'product-images'));
    }

    public function routes(RouteCollection $routes): void
    {
        $routes->group('admin/product-images', ['filter' => 'session'], static function (RouteCollection $routes): void {
            $routes->get('assets/(:segment)', '\\MCMS\\Modules\\ProductImages\\Controllers\\Images::asset/$1');
            $routes->post('(:segment)/upload', '\\MCMS\\Modules\\ProductImages\\Controllers\\Images::upload/$1');
            $routes->post('(:segment)/arrange', '\\MCMS\\Modules\\ProductImages\\Controllers\\Images::arrange/$1');
            $routes->post('(:segment)/(:num)/crop', '\\MCMS\\Modules\\ProductImages\\Controllers\\Images::crop/$1/$2');
            $routes->post('(:segment)/(:num)/remove', '\\MCMS\\Modules\\ProductImages\\Controllers\\Images::remove/$1/$2');
            $routes->get('(:segment)/(:num)/(:segment)', '\\MCMS\\Modules\\ProductImages\\Controllers\\Images::file/$1/$2/$3');
        });
    }
}
