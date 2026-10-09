<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->setPrioritize();
$routes->get('theme-assets/(:segment)/(:segment)/(:any)', 'ThemeAssets::show/$1/$2/$3');

$routes->get('/', 'Home::index');
$routes->match(['get', 'post'], 'install', 'Install::index', ['as' => 'install']);
$routes->get('_health', 'Health::index');
$routes->get('search/results', 'Search::index');

service('auth')->routes($routes);

$routes->group('admin', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->get('/', 'Admin\Dashboard::index', ['as' => 'admin.dashboard']);
    $routes->get('users', 'Admin\Users::index', ['as' => 'admin.users']);
    $routes->match(['get', 'post'], 'users/create', 'Admin\Users::create', ['as' => 'admin.users.create']);
    $routes->match(['get', 'post'], 'users/(:num)/access', 'Admin\Users::access/$1', ['as' => 'admin.users.access']);
    $routes->get('access', 'Admin\Access::index', ['as' => 'admin.access']);
    $routes->post('access/group', 'Admin\Access::saveGroup');
    $routes->post('access/group/delete', 'Admin\Access::deleteGroup');
    $routes->get('modules', 'Admin\Modules::index', ['as' => 'admin.modules']);
    $routes->get('modules/package', 'Admin\Modules::package');
    $routes->post('modules/package', 'Admin\Modules::uploadPackage');
    $routes->post('modules/package/install', 'Admin\Modules::installPackage');
    $routes->post('modules/package/cancel', 'Admin\Modules::cancelPackage');
    $routes->get('modules/(:segment)/update', 'Admin\ModuleUpdates::index/$1');
    $routes->post('modules/(:segment)/update', 'Admin\ModuleUpdates::upload/$1');
    $routes->post('modules/(:segment)/update/queue', 'Admin\ModuleUpdates::queue/$1');
    $routes->post('modules/(:segment)/update/cancel', 'Admin\ModuleUpdates::cancel/$1');
    $routes->post('modules/(:segment)/update/apply', 'Admin\ModuleUpdates::apply/$1');
    $routes->post('modules/(:segment)/update/restore', 'Admin\ModuleUpdates::restore/$1');
    $routes->get('modules/(:segment)/update/snapshot', 'Admin\ModuleUpdates::snapshot/$1');
    $routes->post('modules/(:segment)/enable', 'Admin\Modules::enable/$1');
    $routes->post('modules/(:segment)/disable', 'Admin\Modules::disable/$1');
    $routes->match(['get', 'post'], 'settings', 'Admin\Settings::index', ['as' => 'admin.settings']);
    $routes->get('themes', 'Admin\Themes::index', ['as' => 'admin.themes']);
    $routes->get('themes/package', 'Admin\Themes::package');
    $routes->post('themes/package', 'Admin\Themes::uploadPackage');
    $routes->post('themes/package/install', 'Admin\Themes::installPackage');
    $routes->post('themes/package/cancel', 'Admin\Themes::cancelPackage');
    $routes->get('themes/images', 'Admin\Themes::images');
    $routes->post('themes/(:segment)/appearance', 'Admin\Themes::appearance/$1');
    $routes->post('themes/(:segment)/home', 'Admin\Themes::home/$1');
    $routes->match(['get', 'post'], 'themes/(:segment)', 'Admin\Themes::configure/$1');
    $routes->post('themes/(:segment)/activate', 'Admin\Themes::activate/$1');
    $routes->get('system', 'Admin\System::index', ['as' => 'admin.system']);
});

$moduleManager = service('mcmsModules');
// Applying a replacement must not load old module PHP into the parent request.
// The fresh worker owns target execution; normal requests drain through shutdown.
$coreUpdateRequest = ! is_cli() && preg_match(
    '#^admin/modules/[^/]+/update/(apply|restore)$#D',
    trim(service('request')->getUri()->getPath(), '/'),
) === 1;
if (! $coreUpdateRequest) {
    $moduleManager->bootEnabled();
    $moduleManager->registerRoutes($routes);
}
