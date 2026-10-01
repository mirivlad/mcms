<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MCMS\Core\Themes\MenuSourceInterface;
use MCMS\Core\Themes\ThemeManager;

final class ThemeManagerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = [
        'App',
        'CodeIgniter\\Settings',
    ];

    public function testCatalogActivationAndLogicalRendering(): void
    {
        $manager = new ThemeManager(
            service('settings'),
            ROOTPATH . 'tests/_support/FixtureThemes',
        );

        self::assertSame(['alpha', 'beta'], array_keys($manager->catalog()));
        self::assertNull($manager->active());

        $theme = $manager->activate('alpha');

        self::assertSame('alpha', $theme->id);
        self::assertSame('alpha', $manager->activeId());
        self::assertSame('alpha', $manager->active()?->id);

        $rendered = $manager->render('home', ['message' => '<hello>']);

        self::assertStringContainsString('Alpha: &lt;hello&gt;', $rendered);
        self::assertStringContainsString(
            '/assets/themes/alpha/css/theme.css',
            $manager->assetUrl('css/theme.css'),
        );
    }

    public function testUnknownThemeCannotBeActivated(): void
    {
        $manager = new ThemeManager(
            service('settings'),
            ROOTPATH . 'tests/_support/FixtureThemes',
        );

        $this->expectException(RuntimeException::class);
        $manager->activate('missing');
    }

    public function testMenuAssignmentsUseCoreContractAndRespectThemeDepth(): void
    {
        $source = new class implements MenuSourceInterface {
            public function list(): array
            {
                return [
                    ['id' => 7, 'name' => 'Primary', 'handle' => 'primary'],
                ];
            }

            public function findMenu(int $id): ?array
            {
                return $id === 7
                    ? ['id' => 7, 'name' => 'Primary', 'handle' => 'primary']
                    : null;
            }

            public function tree(int $menuId, bool $enabledOnly = false): array
            {
                if ($menuId !== 7) {
                    return [];
                }

                return [[
                    'id' => 1,
                    'label' => 'Parent',
                    'children' => [[
                        'id' => 2,
                        'label' => 'Child',
                        'children' => [[
                            'id' => 3,
                            'label' => 'Grandchild',
                            'children' => [],
                        ]],
                    ]],
                ]];
            }
        };

        $manager = new ThemeManager(
            service('settings'),
            ROOTPATH . 'tests/_support/FixtureThemes',
            $source,
        );
        $manager->activate('alpha');

        $saved = $manager->saveMenuAssignments('alpha', [
            'header.primary' => 7,
            'footer' => 7,
            'unknown.location' => 7,
        ]);

        self::assertSame([
            'footer' => 7,
            'header.primary' => 7,
        ], $saved);
        self::assertSame($saved, $manager->menuAssignments());

        $trees = $manager->menuTrees();
        self::assertSame('Parent', $trees['header.primary'][0]['label']);
        self::assertSame('Child', $trees['header.primary'][0]['children'][0]['label']);
        self::assertSame([], $trees['header.primary'][0]['children'][0]['children']);
        self::assertSame([], $trees['footer'][0]['children']);
    }

    public function testMenuAssignmentRejectsMissingMenu(): void
    {
        $source = new class implements MenuSourceInterface {
            public function list(): array
            {
                return [];
            }

            public function findMenu(int $id): ?array
            {
                return null;
            }

            public function tree(int $menuId, bool $enabledOnly = false): array
            {
                return [];
            }
        };

        $manager = new ThemeManager(
            service('settings'),
            ROOTPATH . 'tests/_support/FixtureThemes',
            $source,
        );

        $this->expectException(InvalidArgumentException::class);
        $manager->saveMenuAssignments('alpha', ['header.primary' => 999]);
    }
}
