<?php

declare(strict_types=1);

namespace MCMS\Modules\Comments;

use CodeIgniter\Router\RouteCollection;
use MCMS\Core\Modules\ModuleContext;
use MCMS\Core\Modules\ModuleProviderInterface;
use MCMS\Modules\Comments\Services\Comments as Repository;

final class Module implements ModuleProviderInterface
{
    public function boot(ModuleContext $context): void
    {
        $policy = new \MCMS\Core\Content\ResourcePolicy(db_connect(), 'comments', 'Comments.Admin.title', 'comments_resource_options');
        $context->services->register('comments.resource-policy', $policy);
        $context->services->register('comments.repository', new Repository(db_connect(), $context->navigation, $policy));
        $context->admin->addMenu('comments', '@Comments.Admin.title', '/admin/comments', 'ti ti-messages', 'comments.view', 58, group: 'administration', section: 'moderation');
        $context->admin->addSettings('comments', '@Comments.Admin.title', '/admin/comments/settings', 'ti ti-messages', 'comments.moderate', 'content');
    }

    public function routes(RouteCollection $routes): void
    {
        $routes->post('comments/create', '\MCMS\Modules\Comments\Controllers\PublicComments::create');
        $routes->post('comments/report', '\MCMS\Modules\Comments\Controllers\PublicComments::report');
        $routes->group('admin/comments', ['filter' => 'session'], static function (RouteCollection $routes): void {
            $routes->get('/', '\MCMS\Modules\Comments\Controllers\Admin::index');
            $routes->match(['get', 'post'], 'settings', '\MCMS\Modules\Comments\Controllers\Admin::settings');
            $routes->post('(:num)/status', '\MCMS\Modules\Comments\Controllers\Admin::status/$1');
            $routes->post('(:num)/delete', '\MCMS\Modules\Comments\Controllers\Admin::delete/$1');
        });
    }
}
