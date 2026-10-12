<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use MCMS\Modules\Catalogue\Navigation\CategoryMenu;

final class CategoryMenuTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        service('autoloader')->addNamespace('MCMS\\Modules\\Catalogue', ROOTPATH . 'modules/Catalogue');
    }

    public function testTreeIsBuiltFromLiveCategoryRowsInStableOrder(): void
    {
        $rows = [
            ['id' => 1, 'parent_id' => null, 'name' => 'Books', 'slug' => 'books', 'url' => '/shop/category/books'],
            ['id' => 3, 'parent_id' => 1, 'name' => 'Science', 'slug' => 'science', 'url' => '/shop/category/science'],
            ['id' => 4, 'parent_id' => 3, 'name' => 'Physics', 'slug' => 'physics', 'url' => '/shop/category/physics'],
            ['id' => 2, 'parent_id' => null, 'name' => 'Toys', 'slug' => 'toys', 'url' => '/shop/category/toys'],
        ];
        $tree = CategoryMenu::fromRows($rows);
        self::assertSame(['Books', 'Toys'], array_column($tree, 'label'));
        self::assertSame('Science', $tree[0]['children'][0]['label']);
        self::assertSame('Physics', $tree[0]['children'][0]['children'][0]['label']);
        self::assertSame('/shop/category/science', $tree[0]['children'][0]['url']);
        self::assertSame([], CategoryMenu::fromRows([]));
    }

    public function testThreeTreeVariantsRenderAccessibleToggleControls(): void
    {
        $tree = CategoryMenu::fromRows([
            ['id' => 1, 'parent_id' => null, 'name' => 'Books', 'slug' => 'books', 'url' => '/shop/category/books'],
            ['id' => 2, 'parent_id' => 1, 'name' => 'Science', 'slug' => 'science', 'url' => '/shop/category/science'],
        ]);

        foreach (['tree', 'collapsed', 'horizontal'] as $style) {
            $html = view('MCMS\Modules\Catalogue\Views\category_menu', [
                'tree' => $tree, 'style' => $style, 'selectedSlug' => 'science',
            ]);
            self::assertStringContainsString('mcms-shop-cat-' . $style, $html);
            self::assertStringContainsString('shop&#x2F;category&#x2F;science', $html);
            self::assertStringContainsString('aria-current="page"', $html);
            if ($style === 'tree') {
                self::assertStringNotContainsString('data-mcms-cat-toggle', $html);
                self::assertStringNotContainsString('aria-expanded=', $html);
            } else {
                self::assertStringContainsString('data-mcms-cat-toggle', $html);
                self::assertStringContainsString('aria-expanded="false"', $html);
                self::assertStringContainsString('hidden', $html);
                self::assertStringContainsString('aria-controls="shop-categories-node-1"', $html);
            }
        }

        $empty = view('MCMS\Modules\Catalogue\Views\category_menu', [
            'tree' => [], 'style' => 'tree',
        ]);
        self::assertStringNotContainsString('<nav', $empty);
    }
}
