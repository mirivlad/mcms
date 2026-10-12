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
    }

    public function routes(RouteCollection $routes): void
    {
        $routes->get('captcha/image/(:segment)', '\MCMS\Modules\Captcha\Controllers\Image::show/$1');
    }
}
