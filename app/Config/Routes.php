<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->setPrioritize();
$routes->get('/', 'Home::index');
$routes->match(['get', 'post'], 'install', 'Install::index', ['as' => 'install']);
$routes->get('_health', 'Health::index');

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
    $routes->post('modules/(:segment)/enable', 'Admin\Modules::enable/$1');
    $routes->post('modules/(:segment)/disable', 'Admin\Modules::disable/$1');
    $routes->match(['get', 'post'], 'settings', 'Admin\Settings::index', ['as' => 'admin.settings']);
    $routes->get('themes', 'Admin\Themes::index', ['as' => 'admin.themes']);
    $routes->match(['get', 'post'], 'themes/(:segment)', 'Admin\Themes::configure/$1');
    $routes->post('themes/(:segment)/activate', 'Admin\Themes::activate/$1');
    $routes->get('system', 'Admin\System::index', ['as' => 'admin.system']);
});

$moduleManager = service('mcmsModules');
$moduleManager->bootEnabled();
$moduleManager->registerRoutes($routes);
