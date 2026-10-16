<?php

declare(strict_types=1);

namespace MCMS\Modules\Feedback;

use CodeIgniter\Router\RouteCollection;
use MCMS\Core\Modules\ModuleContext;
use MCMS\Core\Modules\ModuleProviderInterface;
use MCMS\Modules\Feedback\Navigation\ContactNavigationProvider;
use MCMS\Modules\Feedback\Services\FeedbackRepository;

final class Module implements ModuleProviderInterface
{
    public function boot(ModuleContext $context): void
    {
        $context->services->register('feedback.repository', new FeedbackRepository(db_connect()));
        $context->navigation->register(new ContactNavigationProvider());
        $context->admin->addMenu('feedback', '@Feedback.Admin.title', '/admin/feedback', 'ti ti-mail', 'feedback.view', 59, group: 'administration', section: 'moderation');
    }

    public function routes(RouteCollection $routes): void
    {
        $routes->group('admin/feedback', ['filter' => 'session'], static function (RouteCollection $routes): void {
            $routes->get('/', '\\MCMS\\Modules\\Feedback\\Controllers\\Admin::index');
            $routes->get('(:num)', '\\MCMS\\Modules\\Feedback\\Controllers\\Admin::show/$1');
            $routes->post('(:num)/read', '\\MCMS\\Modules\\Feedback\\Controllers\\Admin::markRead/$1');
            $routes->post('(:num)/delete', '\\MCMS\\Modules\\Feedback\\Controllers\\Admin::delete/$1');
        });
        $routes->match(['get', 'post'], 'contact', '\\MCMS\\Modules\\Feedback\\Controllers\\Contact::index');
    }
}
