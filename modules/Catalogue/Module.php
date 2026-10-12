<?php

declare(strict_types=1);

namespace MCMS\Modules\Catalogue;

use CodeIgniter\Router\RouteCollection;
use MCMS\Core\Modules\ModuleContext;
use MCMS\Core\Modules\ModuleProviderInterface;
use MCMS\Modules\Catalogue\Navigation\Archive;
use MCMS\Modules\Catalogue\Navigation\Products;
use MCMS\Modules\Catalogue\Navigation\Categories as CategoryNavigation;
use MCMS\Modules\Catalogue\Navigation\CategoryMenu;
use MCMS\Modules\Catalogue\Services\Categories;
use MCMS\Modules\Catalogue\Services\Catalogue;
use MCMS\Modules\Catalogue\Services\PublicSearch;

final class Module implements ModuleProviderInterface
{
    public function boot(ModuleContext $context): void
    {
        $repository = new Catalogue(db_connect(), $context->services->get('product-images.library'));
        $context->services->register('catalogue.repository', $repository);
        $context->services->register('catalogue.exchange', $repository);
        $categories = new Categories(db_connect(), $context->storage->directory('category-icons'));
        $context->services->register('catalogue.categories', $categories);
        $context->services->register('catalogue.menu', new CategoryMenu($categories));
        $context->services->register('catalogue.search', new PublicSearch($repository));
        $context->navigation->register(new Archive());
        $context->navigation->register(new Products($repository));
        $context->navigation->register(new CategoryNavigation($categories));
        $context->admin->addMenu('catalogue', '@Catalogue.manageStore', '/admin/catalogue', 'ti ti-building-store', 'catalogue.view', 65, group: 'commerce');
        $context->admin->addSettings('catalogue', '@CatalogueSettings.title', '/admin/catalogue/settings', 'ti ti-adjustments', 'catalogue.settings', 'commerce');
    }

    public function routes(RouteCollection $routes): void
    {
        $routes->match(['get', 'post'], 'admin/settings/catalogue', '\\MCMS\\Modules\\Catalogue\\Controllers\\Admin::settings', ['filter' => 'session']);
        $routes->group('admin/catalogue', ['filter' => 'session'], static function (RouteCollection $routes): void {
            $routes->get('/', '\\MCMS\\Modules\\Catalogue\\Controllers\\Admin::store');
            $routes->match(['get', 'post'], 'settings', '\\MCMS\\Modules\\Catalogue\\Controllers\\Admin::settings');
            $routes->get('products', '\\MCMS\\Modules\\Catalogue\\Controllers\\Admin::index');
            $routes->get('categories', '\\MCMS\\Modules\\Catalogue\\Controllers\\CategoryAdmin::index');
            $routes->match(['get', 'post'], 'categories/create', '\\MCMS\\Modules\\Catalogue\\Controllers\\CategoryAdmin::create');
            $routes->match(['get', 'post'], 'categories/(:num)/edit', '\\MCMS\\Modules\\Catalogue\\Controllers\\CategoryAdmin::edit/$1');
            $routes->post('categories/(:num)/delete', '\\MCMS\\Modules\\Catalogue\\Controllers\\CategoryAdmin::delete/$1');
            $routes->match(['get', 'post'], 'create', '\\MCMS\\Modules\\Catalogue\\Controllers\\Admin::create');
            $routes->match(['get', 'post'], '(:num)/edit', '\\MCMS\\Modules\\Catalogue\\Controllers\\Admin::edit/$1');
            $routes->post('(:num)/delete', '\\MCMS\\Modules\\Catalogue\\Controllers\\Admin::delete/$1');
        });
        $routes->get('shop/assets/(:segment)', '\\MCMS\\Modules\\Catalogue\\Controllers\\Shop::asset/$1');
        $routes->get('shop', '\\MCMS\\Modules\\Catalogue\\Controllers\\Shop::index');
        $routes->get('shop/category/(:segment)', '\\MCMS\\Modules\\Catalogue\\Controllers\\Shop::category/$1');
        $routes->get('shop/category-icons/(:num)', '\\MCMS\\Modules\\Catalogue\\Controllers\\Shop::categoryIcon/$1');
        $routes->get('shop/(:segment)/images/(:num)/(:segment)', '\\MCMS\\Modules\\Catalogue\\Controllers\\Shop::image/$1/$2/$3');
        $routes->get('shop/(:segment)', '\\MCMS\\Modules\\Catalogue\\Controllers\\Shop::show/$1');
    }
}
