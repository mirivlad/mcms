<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->setPrioritize();
$routes->get('theme-assets/(:segment)/(:segment)/(:any)', 'ThemeAssets::show/$1/$2/$3');

$routes->get('/', 'Home::index');
$routes->match(['get', 'post'], 'install', 'Install::index', ['as' => 'install']);
$routes->get('_health', 'Health::index');
$routes->get('search/results', 'Search::index');

service('auth')->routes($routes, ['except' => ['register', 'login']]);
$routes->get('login', 'Auth\Login::loginView', ['as' => 'login']);
$routes->post('login', 'Auth\Login::loginAction');
$routes->get('register', 'Auth\\Register::registerView', ['as' => 'register']);
$routes->post('register', 'Auth\\Register::registerAction');
$routes->post('logout', 'Account::logout', ['filter' => 'session']);
$routes->group('account', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->get('/', 'Account::index', ['as' => 'account.index']);
    $routes->post('profile', 'Account::saveProfile');
    $routes->post('password', 'Account::changePassword');
    $routes->get('orders', 'Account::orders');
    $routes->get('orders/(:num)', 'Account::order/$1');
});

$routes->group('admin', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->get('/', 'Admin\Dashboard::index', ['as' => 'admin.dashboard']);
    $routes->get('users', 'Admin\Users::index', ['as' => 'admin.users']);
    $routes->match(['get', 'post'], 'users/create', 'Admin\Users::create', ['as' => 'admin.users.create']);
    $routes->match(['get', 'post'], 'users/(:num)/access', 'Admin\Users::access/$1', ['as' => 'admin.users.access']);
    $routes->get('access', 'Admin\Access::index', ['as' => 'admin.access']);
    $routes->post('access/group', 'Admin\Access::saveGroup');
    $routes->post('access/group/delete', 'Admin\Access::deleteGroup');
    $routes->get('extensions', 'Admin\ExtensionCatalog::index');
    $routes->get('extensions/check', 'Admin\ExtensionCatalog::refresh');
    $routes->post('extensions/refresh', 'Admin\ExtensionCatalog::refresh');
    $routes->post('extensions/(:segment)/prepare', 'Admin\ExtensionCatalog::prepare/$1');
    $routes->match(['get', 'post'], 'extensions/recovery', 'Admin\ExtensionRemoval::recovery');
    $routes->match(['get', 'post'], 'extensions/(:segment)/(:segment)/remove', 'Admin\ExtensionRemoval::index/$1/$2');
    $routes->get('extensions/(:segment)', 'Admin\ExtensionCatalog::index/$1');
    $routes->get('modules', 'Admin\Modules::index', ['as' => 'admin.modules']);
    $routes->get('modules/package', 'Admin\Modules::package');
    $routes->post('modules/package', 'Admin\Modules::uploadPackage');
    $routes->post('modules/package/install', 'Admin\Modules::installPackage');
    $routes->post('modules/package/cancel', 'Admin\Modules::cancelPackage');
    $routes->get('modules/(:segment)/update', 'Admin\ModuleUpdates::index/$1');
    $routes->post('modules/(:segment)/update', 'Admin\ModuleUpdates::upload/$1');
    $routes->post('modules/(:segment)/update/queue', 'Admin\ModuleUpdates::queue/$1');
    $routes->post('modules/(:segment)/update/cancel-preview', 'Admin\ModuleUpdates::cancelPreview/$1');
    $routes->post('modules/(:segment)/update/cancel', 'Admin\ModuleUpdates::cancel/$1');
    $routes->post('modules/(:segment)/update/apply', 'Admin\ModuleUpdates::apply/$1');
    $routes->post('modules/(:segment)/update/restore', 'Admin\ModuleUpdates::restore/$1');
    $routes->get('modules/(:segment)/update/snapshot', 'Admin\ModuleUpdates::snapshot/$1');
    $routes->post('modules/(:segment)/enable', 'Admin\Modules::enable/$1');
    $routes->post('modules/(:segment)/disable', 'Admin\Modules::disable/$1');
    $routes->match(['get', 'post'], 'settings', 'Admin\Settings::index', ['as' => 'admin.settings']);
    foreach (['themes', 'settings/themes'] as $themePrefix) {
        $routes->get($themePrefix, 'Admin\Themes::index', ['as' => $themePrefix === 'themes' ? 'admin.themes' : 'admin.settings.themes']);
        $routes->get($themePrefix . '/package', 'Admin\Themes::package');
        $routes->post($themePrefix . '/package', 'Admin\Themes::uploadPackage');
        $routes->post($themePrefix . '/package/install', 'Admin\Themes::installPackage');
        $routes->post($themePrefix . '/package/cancel', 'Admin\Themes::cancelPackage');
        $routes->match(['get', 'post'], $themePrefix . '/(:segment)/update', 'Admin\Themes::update/$1');
        $routes->post($themePrefix . '/(:segment)/update/cancel', 'Admin\Themes::cancelUpdate/$1');
        $routes->post($themePrefix . '/(:segment)/update/apply', 'Admin\Themes::applyUpdate/$1');
        $routes->post($themePrefix . '/(:segment)/rollback', 'Admin\Themes::rollback/$1');
        $routes->get($themePrefix . '/package/resources', 'Admin\Themes::resources');
        $routes->get($themePrefix . '/images', 'Admin\Themes::images');
        $routes->post($themePrefix . '/(:segment)/appearance', 'Admin\Themes::appearance/$1');
        $routes->post($themePrefix . '/(:segment)/home', 'Admin\Themes::home/$1');
        $routes->match(['get', 'post'], $themePrefix . '/(:segment)', 'Admin\Themes::configure/$1');
        $routes->post($themePrefix . '/(:segment)/activate', 'Admin\Themes::activate/$1');
    }
    $routes->get('system', 'Admin\System::index', ['as' => 'admin.system']);
    $routes->get('backups', 'Admin\Backups::index', ['as' => 'admin.backups']);
    $routes->post('backups/create', 'Admin\Backups::create');
    $routes->get('backups/(:segment)/download', 'Admin\Backups::download/$1');
    $routes->post('backups/(:segment)/delete', 'Admin\Backups::delete/$1');
    $routes->post('backups/(:segment)/restore', 'Admin\Backups::prepareExisting/$1');
    $routes->post('backups/restore/upload', 'Admin\Backups::prepareUpload');
    $routes->get('backups/restore/confirm', 'Admin\Backups::confirm');
    $routes->post('backups/restore/apply', 'Admin\Backups::apply');
});

$moduleManager = service('mcmsModules');
// Applying a replacement must not load old module PHP into the parent request.
// The fresh worker owns target execution; normal requests drain through shutdown.
$coreUpdateRequest = ! is_cli() && preg_match(
    '#^admin/(modules/[^/]+/update/(apply|restore)|extensions/[^/]+/[^/]+/remove|extensions/recovery)$#D',
    trim(service('request')->getUri()->getPath(), '/'),
) === 1;
if (! $coreUpdateRequest && ! is_file(WRITEPATH . 'backups/restore-active.json')) {
    $moduleManager->bootEnabled();
    $moduleManager->registerRoutes($routes);
}
