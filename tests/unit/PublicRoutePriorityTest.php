<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;
use MCMS\Modules\Pages\Module;

final class PublicRoutePriorityTest extends CIUnitTestCase
{
    public function testFixedRouteRegisteredAfterPagesStillWins(): void
    {
        $routes = Services::routes(false);
        $routes->setPrioritize();
        (new Module())->routes($routes);
        $routes->get('later', 'Later::index');
        $paths = array_keys($routes->getRoutes('GET'));

        self::assertLessThan(array_search('([^/]+)', $paths, true), array_search('later', $paths, true));
    }
}
