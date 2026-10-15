<?php

declare(strict_types=1);

namespace MCMS\Modules\Ratings;

use CodeIgniter\Router\RouteCollection;
use MCMS\Core\Modules\ModuleContext;
use MCMS\Core\Modules\ModuleProviderInterface;
use MCMS\Modules\Ratings\Services\Ratings as Repository;

final class Module implements ModuleProviderInterface
{
    public function boot(ModuleContext $context): void
    {
        $policy = new \MCMS\Core\Content\ResourcePolicy(db_connect(), 'ratings', 'Ratings.Admin.title', 'ratings_resource_options');
        $context->services->register('ratings.resource-policy', $policy);
        $context->services->register('ratings.repository', new Repository(db_connect(), $context->navigation, $policy));
        $context->admin->addMenu('ratings', '@Ratings.Admin.title', '/admin/ratings', 'ti ti-star', 'ratings.view', 59, group: 'administration');
        $context->admin->addSettings('ratings', '@Ratings.Admin.title', '/admin/ratings/settings', 'ti ti-star', 'ratings.manage', 'content');
    }

    public function routes(RouteCollection $routes): void
    {
        $routes->post('ratings/vote', '\MCMS\Modules\Ratings\Controllers\PublicRatings::vote');
        $routes->group('admin/ratings', ['filter' => 'session'], static function (RouteCollection $routes): void {
            $routes->get('/', '\MCMS\Modules\Ratings\Controllers\Admin::index');
            $routes->match(['get', 'post'], 'settings', '\MCMS\Modules\Ratings\Controllers\Admin::settings');
            $routes->post('(:num)/delete', '\MCMS\Modules\Ratings\Controllers\Admin::delete/$1');
        });
    }
}
