<?php

declare(strict_types=1);

namespace MCMS\Modules\Captcha;

use CodeIgniter\Router\RouteCollection;
use MCMS\Core\Modules\ModuleContext;
use MCMS\Core\Modules\ModuleProviderInterface;
use MCMS\Modules\Captcha\Services\Captcha as CaptchaService;

final class Module implements ModuleProviderInterface
{
    public function boot(ModuleContext $context): void
    {
        $context->services->register('captcha.verifier', new CaptchaService(db_connect()));
        $context->admin->addMenu('captcha', '@Captcha.Admin.name', '/admin/captcha', 'ti ti-shield-check', 'captcha.manage', 60, group: 'administration');
        $context->admin->addSettings('captcha', '@Captcha.Admin.name', '/admin/captcha', 'ti ti-shield-check', 'captcha.manage', 'content');
    }

    public function routes(RouteCollection $routes): void
    {
        $routes->match(['get', 'post'], 'admin/captcha', '\MCMS\Modules\Captcha\Controllers\Admin::index', ['filter' => 'session']);
        $routes->match(['get', 'post'], 'admin/settings/captcha', '\MCMS\Modules\Captcha\Controllers\Admin::index', ['filter' => 'session']);
        $routes->get('captcha/image/(:segment)', '\MCMS\Modules\Captcha\Controllers\Image::show/$1');
    }
}
