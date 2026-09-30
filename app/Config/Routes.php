<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->get('_health', 'Health::index');

service('auth')->routes($routes);

$routes->group('admin', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->get('/', 'Admin\Dashboard::index', ['as' => 'admin.dashboard']);
    $routes->get('modules', 'Admin\Modules::index', ['as' => 'admin.modules']);
    $routes->post('modules/(:segment)/enable', 'Admin\Modules::enable/$1');
    $routes->post('modules/(:segment)/disable', 'Admin\Modules::disable/$1');
});

$moduleManager = service('mcmsModules');
$moduleManager->bootEnabled();
$moduleManager->registerRoutes($routes);
