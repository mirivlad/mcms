<?php

declare(strict_types=1);

namespace Tests\Support\FixtureModules\Alpha;

use CodeIgniter\Router\RouteCollection;
use MCMS\Core\Modules\ModuleContext;
use MCMS\Core\Modules\ModuleProviderInterface;

final class Module implements ModuleProviderInterface
{
    public function boot(ModuleContext $context): void
    {
        $context->services->register('alpha.api', new AlphaApi());

        $context->admin->addMenu(
            'alpha',
            'Alpha Fixture',
            '/alpha',
            permission: null,
            order: 500,
        );
    }

    public function routes(RouteCollection $routes): void
    {
        // Runtime route registration is exercised by the same provider contract;
        // this fixture needs no HTTP endpoint.
    }
}
