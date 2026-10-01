<?php

declare(strict_types=1);

namespace MCMS\Modules\Menus;

use CodeIgniter\Router\RouteCollection;
use MCMS\Core\Modules\ModuleContext;
use MCMS\Core\Modules\ModuleProviderInterface;
use MCMS\Modules\Menus\Services\MenuRepository;

final class Module implements ModuleProviderInterface
{
    public function boot(ModuleContext $context): void
    {
        $context->services->register(
            'menus.repository',
            new MenuRepository(db_connect()),
        );

        $context->admin->addMenu(
            'menus',
            '@Menus.Admin.menuLabel',
            '/admin/menus',
            'ti ti-menu-2',
            'menus.view',
            60,
        );
    }

    public function routes(RouteCollection $routes): void
    {
        $routes->group('admin/menus', ['filter' => 'session'], static function (RouteCollection $routes): void {
            $routes->get('/', '\\MCMS\\Modules\\Menus\\Controllers\\Admin::index');
            $routes->match(['get', 'post'], 'create', '\\MCMS\\Modules\\Menus\\Controllers\\Admin::create');
            $routes->match(['get', 'post'], '(:num)/edit', '\\MCMS\\Modules\\Menus\\Controllers\\Admin::edit/$1');
            $routes->post('(:num)/delete', '\\MCMS\\Modules\\Menus\\Controllers\\Admin::delete/$1');
            $routes->post('(:num)/items/save', '\\MCMS\\Modules\\Menus\\Controllers\\Admin::saveItem/$1');
            $routes->post('(:num)/items/(:num)/delete', '\\MCMS\\Modules\\Menus\\Controllers\\Admin::deleteItem/$1/$2');
            $routes->post('(:num)/structure', '\\MCMS\\Modules\\Menus\\Controllers\\Admin::saveStructure/$1');
        });
    }
}