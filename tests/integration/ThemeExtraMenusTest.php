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
}
