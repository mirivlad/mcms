<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Modules\ModuleManager;

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
        $this->setUpMethods[] = 'setUpFixtureNamespace';

        parent::setUp();
    }

    protected function setUpFixtureNamespace(): void
    {
        service('autoloader')->addNamespace(
            'Tests\Support\FixtureModules\Alpha',
            ROOTPATH . 'tests/_support/FixtureModules/Alpha',
        );
    }

    protected function tearDown(): void
    {
        $this->migrations
            ->setNamespace('Tests\Support\FixtureModules\Alpha')
            ->regress(0, 'tests');

        parent::tearDown();
    }

    public function testEnableDisableAndReenablePreserveModuleDataAndSettings(): void
    {
        $modulesPath = ROOTPATH . 'tests/_support/FixtureModules';

        $admin = new AdminRegistry();
        $manager = new ModuleManager($this->db, $admin, $modulesPath);

        self::assertArrayHasKey('alpha', $manager->catalog());
        self::assertSame([], $manager->states());

        $manager->enable('alpha');

        self::assertTrue($this->db->tableExists('alpha_records'));
        self::assertSame(25, setting('alpha.items_per_page'));
        self::assertSame(1, (int) $manager->states()['alpha']['enabled']);

        $manager->bootEnabled();
        self::assertContains('alpha', array_column($admin->menu(), 'id'));

        $this->db->table('alpha_records')->insert(['name' => 'preserved']);
        service('settings')->set('alpha.items_per_page', 99);

        $manager->disable('alpha');

        self::assertSame(0, (int) $manager->states()['alpha']['enabled']);
        self::assertTrue($this->db->tableExists('alpha_records'));
        self::assertSame(1, $this->db->table('alpha_records')->countAllResults());

        $disabledAdmin = new AdminRegistry();
        $disabledManager = new ModuleManager($this->db, $disabledAdmin, $modulesPath);
        $disabledManager->bootEnabled();

        self::assertNotContains('alpha', array_column($disabledAdmin->menu(), 'id'));

        $disabledManager->enable('alpha');

        self::assertSame(1, $this->db->table('alpha_records')->countAllResults());
        self::assertSame(99, setting('alpha.items_per_page'));

        $enabledAdmin = new AdminRegistry();
        $enabledManager = new ModuleManager($this->db, $enabledAdmin, $modulesPath);
        $enabledManager->bootEnabled();

        self::assertContains('alpha', array_column($enabledAdmin->menu(), 'id'));
    }
}
