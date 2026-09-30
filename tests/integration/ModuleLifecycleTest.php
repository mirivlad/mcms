<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModuleServiceRegistry;
use Tests\Support\FixtureModules\Beta\BetaApi;

final class ModuleLifecycleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = [
        'App',
        'CodeIgniter\Settings',
    ];

    protected function setUp(): void
    {
        $this->setUpMethods[] = 'setUpFixtureNamespaces';

        parent::setUp();
    }

    protected function setUpFixtureNamespaces(): void
    {
        service('autoloader')->addNamespace(
            'Tests\Support\FixtureModules\Alpha',
            ROOTPATH . 'tests/_support/FixtureModules/Alpha',
        );
        service('autoloader')->addNamespace(
            'Tests\Support\FixtureModules\Beta',
            ROOTPATH . 'tests/_support/FixtureModules/Beta',
        );
    }

    protected function tearDown(): void
    {
        $this->migrations
            ->setNamespace('Tests\Support\FixtureModules\Beta')
            ->regress(0, 'tests');

        $this->migrations
            ->setNamespace('Tests\Support\FixtureModules\Alpha')
            ->regress(0, 'tests');

        parent::tearDown();
    }

    public function testDependenciesBootBeforeDependantsAndDisablePreservesData(): void
    {
        $modulesPath = ROOTPATH . 'tests/_support/FixtureModules';

        $admin = new AdminRegistry();
        $services = new ModuleServiceRegistry();
        $manager = new ModuleManager($this->db, $admin, $services, $modulesPath);

        self::assertArrayHasKey('alpha', $manager->catalog());
        self::assertArrayHasKey('beta', $manager->catalog());
        self::assertSame([], $manager->states());

        // Enabling Beta must recursively install/enable Alpha first.
        $manager->enable('beta');

        self::assertTrue($this->db->tableExists('alpha_records'));
        self::assertTrue($this->db->tableExists('beta_records'));
        self::assertSame(25, setting('alpha.items_per_page'));
        self::assertSame(1, (int) $manager->states()['alpha']['enabled']);
        self::assertSame(1, (int) $manager->states()['beta']['enabled']);

        // Runtime boot order must follow dependencies, not alphabetical luck.
        $manager->bootEnabled();

        self::assertTrue($services->has('alpha.api'));
        self::assertTrue($services->has('beta.api'));
        $betaApi = $services->get('beta.api');
        self::assertInstanceOf(BetaApi::class, $betaApi);
        self::assertSame('alpha', $betaApi->dependencyName());
        self::assertSame(['alpha', 'beta'], array_values(array_intersect(
            array_column($admin->menu(), 'id'),
            ['alpha', 'beta'],
        )));

        $this->db->table('alpha_records')->insert(['name' => 'preserved']);
        service('settings')->set('alpha.items_per_page', 99);

        // A dependency cannot be disabled while an enabled dependant needs it.
        try {
            $manager->disable('alpha');
            self::fail('Disabling Alpha while Beta depends on it should fail.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('beta', strtolower($e->getMessage()));
        }

        $manager->disable('beta');
        $manager->disable('alpha');

        self::assertSame(0, (int) $manager->states()['alpha']['enabled']);
        self::assertSame(0, (int) $manager->states()['beta']['enabled']);
        self::assertTrue($this->db->tableExists('alpha_records'));
        self::assertTrue($this->db->tableExists('beta_records'));
        self::assertSame(1, $this->db->table('alpha_records')->countAllResults());

        $disabledAdmin = new AdminRegistry();
        $disabledServices = new ModuleServiceRegistry();
        $disabledManager = new ModuleManager(
            $this->db,
            $disabledAdmin,
            $disabledServices,
            $modulesPath,
        );
        $disabledManager->bootEnabled();

        self::assertFalse($disabledServices->has('alpha.api'));
        self::assertFalse($disabledServices->has('beta.api'));
        self::assertNotContains('alpha', array_column($disabledAdmin->menu(), 'id'));
        self::assertNotContains('beta', array_column($disabledAdmin->menu(), 'id'));

        // Re-enabling Beta must re-enable Alpha, preserve data and keep
        // user-owned settings instead of writing defaults again.
        $disabledManager->enable('beta');

        self::assertSame(1, $this->db->table('alpha_records')->countAllResults());
        self::assertSame(99, setting('alpha.items_per_page'));

        $enabledAdmin = new AdminRegistry();
        $enabledServices = new ModuleServiceRegistry();
        $enabledManager = new ModuleManager(
            $this->db,
            $enabledAdmin,
            $enabledServices,
            $modulesPath,
        );
        $enabledManager->bootEnabled();

        self::assertTrue($enabledServices->has('alpha.api'));
        self::assertTrue($enabledServices->has('beta.api'));
        self::assertSame(['alpha', 'beta'], array_values(array_intersect(
            array_column($enabledAdmin->menu(), 'id'),
            ['alpha', 'beta'],
        )));
    }
}
