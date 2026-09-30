<?php

declare(strict_types=1);

namespace Tests\Support\FixtureModules\Beta;

use CodeIgniter\Router\RouteCollection;
use MCMS\Core\Modules\ModuleContext;
use MCMS\Core\Modules\ModuleProviderInterface;
use RuntimeException;
use Tests\Support\FixtureModules\Alpha\AlphaApi;

final class Module implements ModuleProviderInterface
{
    public function boot(ModuleContext $context): void
    {
        $alpha = $context->services->get('alpha.api');

        if (! $alpha instanceof AlphaApi) {
            throw new RuntimeException('Alpha public API was not booted before Beta.');
        }

        $context->services->register('beta.api', new BetaApi($alpha->name()));

        $context->admin->addMenu(
            'beta',
            'Beta Fixture',
            '/beta',
            permission: null,
            order: 600,
        );
    }

    public function routes(RouteCollection $routes): void
    {
    }
}
