<?php

declare(strict_types=1);

namespace MCMS\Modules\LifecycleDemo;

use CodeIgniter\Router\RouteCollection;
use MCMS\Core\Modules\ModuleContext;
use MCMS\Core\Modules\ModuleProviderInterface;

final class Module implements ModuleProviderInterface
{
    public function boot(ModuleContext $context): void
    {
        // A real file survives disable/removal/reinstallation in scoped storage.
        if (! $context->storage->exists('welcome.txt')) {
            $context->storage->put('welcome.txt', 'Lifecycle demo data');
        }
    }

    public function routes(RouteCollection $routes): void
    {
    }
}
