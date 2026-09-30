<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->match(['get', 'post'], 'install', 'Install::index', ['as' => 'install']);
$routes->get('_health', 'Health::index');

service('auth')->routes($routes);

$routes->group('admin', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->get('/', 'Admin\Dashboard::index', ['as' => 'admin.dashboard']);
    $routes->get('users', 'Admin\Users::index', ['as' => 'admin.users']);
    $routes->get('modules', 'Admin\Modules::index', ['as' => 'admin.modules']);
    $routes->post('modules/(:segment)/enable', 'Admin\Modules::enable/$1');
    $routes->post('modules/(:segment)/disable', 'Admin\Modules::disable/$1');
    $routes->match(['get', 'post'], 'settings', 'Admin\Settings::index', ['as' => 'admin.settings']);
    $routes->get('system', 'Admin\System::index', ['as' => 'admin.system']);
});

$moduleManager = service('mcmsModules');
$moduleManager->bootEnabled();
$moduleManager->registerRoutes($routes);
