<?php

declare(strict_types=1);

namespace MCMS\Modules\Cart;

use CodeIgniter\Router\RouteCollection;
use MCMS\Core\Modules\ModuleContext;
use MCMS\Core\Modules\ModuleProviderInterface;
use MCMS\Modules\Cart\Services\Cart;

final class Module implements ModuleProviderInterface
{
    public function boot(ModuleContext $context): void
    {
        $context->services->register(
            'cart.cart',
            new Cart(
                $context->services->get('catalogue.repository'),
                $context->services->get('stock.inventory'),
            )
        );
    }

    public function routes(RouteCollection $routes): void
    {
        $routes->get('cart/fragment', '\\MCMS\\Modules\\Cart\\Controllers\\Cart::fragment');
        $routes->get('cart', '\\MCMS\\Modules\\Cart\\Controllers\\Cart::index');
        $routes->post('cart/add', '\\MCMS\\Modules\\Cart\\Controllers\\Cart::add');
        $routes->post('cart/update', '\\MCMS\\Modules\\Cart\\Controllers\\Cart::update');
        $routes->post('cart/remove/(:num)', '\\MCMS\\Modules\\Cart\\Controllers\\Cart::remove/$1');
        $routes->post('cart/clear', '\\MCMS\\Modules\\Cart\\Controllers\\Cart::clear');
        $routes->get('cart/count', '\\MCMS\\Modules\\Cart\\Controllers\\Cart::count');
        $routes->get('cart/assets/(:segment)', '\\MCMS\\Modules\\Cart\\Controllers\\Cart::asset/$1');
    }
}
