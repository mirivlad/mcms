<?php

declare(strict_types=1);

namespace MCMS\Modules\Analytics;

use CodeIgniter\Router\RouteCollection;
use MCMS\Core\Modules\ModuleContext;
use MCMS\Core\Modules\ModuleProviderInterface;
use MCMS\Modules\Analytics\Filters\TrackVisits;
use MCMS\Modules\Analytics\Services\Analytics;

final class Module implements ModuleProviderInterface
{
    public function boot(ModuleContext $context): void
    {
        $context->services->register('analytics.statistics', new Analytics(db_connect(), $context->navigation));
        $context->admin->addMenu('analytics', '@Analytics.title', '/admin/analytics', 'ti ti-chart-line', 'analytics.view', 10, group: 'main', overviewSection: 'maintenance');
        $filters = config('Filters');
        $filters->aliases['analyticsVisits'] = TrackVisits::class;
        $filters->globals['after'][] = 'analyticsVisits';
    }

    public function routes(RouteCollection $routes): void
    {
        $routes->get('admin/analytics', '\\MCMS\\Modules\\Analytics\\Controllers\\Admin::index', ['filter' => 'session']);
        $routes->get('analytics/privacy', '\\MCMS\\Modules\\Analytics\\Controllers\\Privacy::index');
        $routes->post('analytics/privacy', '\\MCMS\\Modules\\Analytics\\Controllers\\Privacy::save');
    }
}
