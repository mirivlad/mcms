<?php

declare(strict_types=1);

namespace MCMS\Core\Modules;

use CodeIgniter\Router\RouteCollection;

interface ModuleProviderInterface
{
    public function boot(ModuleContext $context): void;

    public function routes(RouteCollection $routes): void;
}
