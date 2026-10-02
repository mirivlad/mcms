<?php

declare(strict_types=1);

namespace MCMS\Modules\Seo;

use CodeIgniter\Events\Events;
use CodeIgniter\Router\RouteCollection;
use MCMS\Core\Events\CoreEvents;
use MCMS\Core\Modules\ModuleContext;
use MCMS\Core\Modules\ModuleProviderInterface;
use MCMS\Modules\Seo\Filters\Redirects;
use MCMS\Modules\Seo\Services\RedirectRepository;

final class Module implements ModuleProviderInterface
{
    public function boot(ModuleContext $context): void
    {
        $redirects = new RedirectRepository(db_connect(), $context->navigation);
        $context->services->register('seo.redirects', $redirects);
        $context->services->register('seo.metadata', new \MCMS\Modules\Seo\Services\Metadata());
        Events::on(CoreEvents::RESOURCE_MOVED, static function (string $provider, string $resourceId, string $oldUrl, string $newUrl) use ($redirects): void {
            $redirects->record($provider, $resourceId, $oldUrl);
        });
        $filters = config('Filters');
        $filters->aliases['seoRedirects'] = Redirects::class;
        $filters->globals['before'][] = 'seoRedirects';
        $context->admin->addMenu('seo', '@Seo.Admin.title', '/admin/seo', 'ti ti-link', 'seo.manage', 64);
    }

    public function routes(RouteCollection $routes): void
    {
        $routes->get('sitemap.xml', '\\MCMS\\Modules\\Seo\\Controllers\\Sitemap::index');
        $routes->group('admin/seo', ['filter' => 'session'], static function (RouteCollection $routes): void {
            $routes->get('/', '\\MCMS\\Modules\\Seo\\Controllers\\Admin::index');
            $routes->post('settings', '\\MCMS\\Modules\\Seo\\Controllers\\Admin::settings');
            $routes->post('delete', '\\MCMS\\Modules\\Seo\\Controllers\\Admin::delete');
        });
    }
}
