<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MCMS\Core\Themes\DynamicMenuSourceInterface;
use MCMS\Core\Themes\MenuSourceInterface;
use MCMS\Core\Themes\ThemeManager;

final class ThemeExtraMenusTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\Settings'];

    protected function tearDown(): void
    {
        service('settings')->forgetMany(['Core.theme', 'Core.themeExtraMenus', 'Core.themeMenuAssignments']);
        \Config\Services::resetSingle('mcmsModuleServices');
        parent::tearDown();
    }

    private function manager(): ThemeManager
    {
        $services = service('mcmsModuleServices');
        if (! $services->has('testing.category.menu')) {
            $services->register('testing.category.menu', new class implements DynamicMenuSourceInterface {
                public function id(): string { return 'testing.categories'; }
                public function label(): string { return 'Generated categories'; }
                public function tree(): array
                {
                    return [[
                        'label' => 'Books', 'url' => '/shop/category/books',
                        'children' => [[
                            'label' => 'Science', 'url' => '/shop/category/science',
                            'children' => [['label' => 'Physics', 'url' => '/shop/category/physics', 'children' => []]],
                        ]],
                    ]];
                }
            });
        }

        $source = new class implements MenuSourceInterface {
            public function list(): array
            {
                return [['id' => 7, 'name' => 'About menu', 'handle' => 'about']];
            }
            public function findMenu(int $id): ?array
            {
                return $id === 7 ? ['id' => 7, 'name' => 'About menu', 'handle' => 'about'] : null;
            }
            public function tree(int $menuId, bool $enabledOnly = false): array
            {
                return $menuId === 7 ? [['label' => 'About us', 'url' => '/about', 'children' => []]] : [];
            }
        };

        $manager = new ThemeManager(service('settings'), ROOTPATH . 'tests/_support/FixtureThemes', $source);
        $manager->activate('alpha');
        return $manager;
    }

    public function testGeneratedMenusRemainScopedAndPreservePrimaryAssignments(): void
    {
        $manager = $this->manager();
        self::assertSame('Generated categories', $manager->availableExtraMenuSources()['dynamic:testing.categories']);
        self::assertSame('About menu', $manager->availableExtraMenuSources()['menu:7']);

        $manager->saveMenuAssignments('alpha', ['header.primary' => 7]);
        $manager->saveExtraMenuAssignments('alpha', [
            'header.primary' => [
                ['source' => 'dynamic:testing.categories', 'visibility' => 'shop'],
                ['source' => 'menu:7', 'visibility' => 'news'],
            ],
            'footer' => [['source' => 'dynamic:testing.categories', 'visibility' => 'all']],
        ]);

        $pageTrees = $manager->menuTrees(null, 'page');
        self::assertSame(['About us'], array_column($pageTrees['header.primary'], 'label'));
        self::assertSame(['Books'], array_column($pageTrees['footer'], 'label'));
        self::assertSame([], $pageTrees['footer'][0]['children']);

        $shopTrees = $manager->menuTrees(null, 'catalogue.index');
        self::assertSame(['About us', 'Books'], array_column($shopTrees['header.primary'], 'label'));
        self::assertSame('Science', $shopTrees['header.primary'][1]['children'][0]['label']);
        self::assertSame([], $shopTrees['header.primary'][1]['children'][0]['children']);
        self::assertSame(7, $manager->menuAssignments()['header.primary']);

        $newsTrees = $manager->menuTrees(null, 'news.article');
        self::assertSame(['About us', 'About us'], array_column($newsTrees['header.primary'], 'label'));
        self::assertSame(2, count($manager->extraMenuAssignments()['header.primary']));
    }

    public function testInvalidSourcesDoNotOverwriteSavedExtraMenus(): void
    {
        $manager = $this->manager();
        $manager->saveMenuAssignments('alpha', []);
        $saved = [['source' => 'dynamic:testing.categories', 'visibility' => 'shop']];
        $manager->saveExtraMenuAssignments('alpha', ['header.primary' => $saved]);

        foreach ([
            ['source' => 'dynamic:unregistered.module', 'visibility' => 'shop'],
            ['source' => 'dynamic:testing.categories', 'visibility' => 'not-a-scope'],
        ] as $bad) {
            try {
                $manager->saveExtraMenuAssignments('alpha', ['header.primary' => [$bad]]);
                self::fail('Invalid extra menu assignment accepted');
            } catch (InvalidArgumentException) {
                self::assertSame($saved, $manager->extraMenuAssignments()['header.primary']);
            }
        }

        // Simulate a previously saved but now absent external menu provider.
        $all = service('settings')->get('Core.themeExtraMenus');
        $all['alpha']['header.primary'] = [['source' => 'dynamic:missing.provider', 'visibility' => 'shop']];
        service('settings')->set('Core.themeExtraMenus', $all);
        self::assertSame(['source' => 'dynamic:missing.provider', 'visibility' => 'shop'],
            $manager->extraMenuAssignments()['header.primary'][0]);
        self::assertSame([], $manager->menuTrees(null, 'catalogue.index')['header.primary']);
    }
    public function testMoreThanTwoExtrasKeepTheirOrderAndPrimaryMenu(): void
    {
        $manager = $this->manager();
        $manager->saveMenuAssignments('alpha', ['header.primary' => 7]);
        $entries = [];
        foreach (range(1, 5) as $n) {
            $source = new class($n) implements DynamicMenuSourceInterface {
                public function __construct(private int $n) {}
                public function id(): string { return 'extra.source' . $this->n; }
                public function label(): string { return 'Extra ' . $this->n; }
                public function tree(): array { return [['label' => $this->label(), 'url' => '/extra-' . $this->n, 'children' => []]]; }
            };
            service('mcmsModuleServices')->register('test-extra-' . $n, $source);
            $entries[] = ['source' => 'dynamic:' . $source->id(), 'visibility' => 'all'];
        }
        $manager->saveNavigationAssignments('alpha', ['header.primary' => 7], ['header.primary' => array_reverse($entries)]);
        self::assertSame(['About us', 'Extra 5', 'Extra 4', 'Extra 3', 'Extra 2', 'Extra 1'], array_column($manager->menuTrees(null, 'page')['header.primary'], 'label'));
        self::assertSame(7, $manager->menuAssignments()['header.primary']);
        try {
            $manager->saveNavigationAssignments('alpha', [], ['header.primary' => [['source' => 'invalid', 'visibility' => 'all']]]);
            self::fail('Invalid extras accepted');
        } catch (InvalidArgumentException) {
        }
        self::assertSame(7, $manager->menuAssignments()['header.primary']);
        self::assertCount(5, $manager->extraMenuAssignments()['header.primary']);
    }

    public function testResourceConditionsUseRegistryAndSurviveUnavailableTargets(): void
    {
        $manager = $this->manager();
        $navigation = new \MCMS\Core\Navigation\NavigableResourceRegistry();
        $provider = new class implements \MCMS\Core\Navigation\NavigableResourceProviderInterface {
            public bool $published = true;
            public function id(): string { return 'external.material'; }
            public function label(): string { return 'External materials'; }
            public function search(string $query = '', int $limit = 50): array { return []; }
            public function resolve(string $resourceId): ?array {
                return $this->published && in_array($resourceId, ['one', 'two'], true)
                    ? ['id' => $resourceId, 'label' => 'Material ' . $resourceId, 'url' => '/material/' . $resourceId] : null;
            }
        };
        $navigation->register($provider);
        \Config\Services::injectMock('mcmsNavigation', $navigation);
        try {
            $manager->saveMenuAssignments('alpha', []);
            $entries = [];
            foreach (['one', 'two'] as $id) {
                $entries[] = ['source' => 'menu:7', 'visibility' => 'resource', 'target_type' => $provider->id(), 'target_id' => $id];
            }
            $manager->saveExtraMenuAssignments('alpha', ['footer' => $entries]);
            self::assertSame([], $manager->menuTrees(null, 'page')['footer']);
            self::assertSame(['About us'], array_column($manager->menuTrees(null, 'page', ['type' => $provider->id(), 'id' => 'one'])['footer'], 'label'));
            self::assertSame(['About us'], array_column($manager->menuTrees(null, 'page', ['type' => $provider->id(), 'id' => 'two'])['footer'], 'label'));
            $provider->published = false;
            self::assertSame([], $manager->menuTrees(null, 'page', ['type' => $provider->id(), 'id' => 'one'])['footer']);
            $manager->saveExtraMenuAssignments('alpha', ['footer' => $entries]);
            self::assertSame($entries, $manager->extraMenuAssignments()['footer']);
            try {
                $manager->saveExtraMenuAssignments('alpha', ['footer' => [[...$entries[0], 'target_id' => 'unpublished']]]);
                self::fail('Unavailable new target accepted');
            } catch (InvalidArgumentException) {
            }
            \Config\Services::injectMock('mcmsNavigation', new \MCMS\Core\Navigation\NavigableResourceRegistry());
            self::assertSame([], $manager->menuTrees(null, 'page', ['type' => $provider->id(), 'id' => 'one'])['footer']);
            \Config\Services::injectMock('mcmsNavigation', $navigation);
            $provider->published = true;
            self::assertCount(1, $manager->menuTrees(null, 'page', ['type' => $provider->id(), 'id' => 'one'])['footer']);
        } finally {
            \Config\Services::resetSingle('mcmsNavigation');
        }
    }

}
