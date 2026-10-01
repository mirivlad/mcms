<?php

declare(strict_types=1);

namespace MCMS\Modules\Pages;

use CodeIgniter\Router\RouteCollection;
use MCMS\Core\Modules\ModuleContext;
use MCMS\Core\Modules\ModuleProviderInterface;
use MCMS\Modules\Pages\Navigation\PageNavigationProvider;
use MCMS\Modules\Pages\Services\HtmlSanitizer;
use MCMS\Modules\Pages\Services\PageRepository;

final class Module implements ModuleProviderInterface
{
    public function boot(ModuleContext $context): void
    {
        $repository = new PageRepository(
            db_connect(),
            new HtmlSanitizer(),
        );

        $context->services->register('pages.repository', $repository);
        $context->navigation->register(new PageNavigationProvider($repository));

        $context->admin->addMenu(
            'pages',
            '@Pages.Admin.menuLabel',
            '/admin/pages',
            'ti ti-file-text',
            'pages.view',
            55,
        );
    }

    public function routes(RouteCollection $routes): void
    {
        $routes->group('admin/pages', ['filter' => 'session'], static function (RouteCollection $routes): void {
            $routes->get('/', '\\MCMS\\Modules\\Pages\\Controllers\\Admin::index');
            $routes->match(['get', 'post'], 'create', '\\MCMS\\Modules\\Pages\\Controllers\\Admin::create');
            $routes->match(['get', 'post'], 'settings', '\\MCMS\\Modules\\Pages\\Controllers\\Admin::settings');
            $routes->get('media', '\\MCMS\\Modules\\Pages\\Controllers\\Admin::media');
            $routes->match(['get', 'post'], '(:num)/edit', '\\MCMS\\Modules\\Pages\\Controllers\\Admin::edit/$1');
            $routes->post('(:num)/delete', '\\MCMS\\Modules\\Pages\\Controllers\\Admin::delete/$1');
        });

        // Registered after Core and Shield routes, so their fixed paths win.
        // PageRepository also rejects reserved Core route slugs.
        $routes->get('(:segment)', '\\MCMS\\Modules\\Pages\\Controllers\\Page::show/$1');
    }
}
