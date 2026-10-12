<?php

declare(strict_types=1);

namespace MCMS\Modules\Catalogue\Navigation;

use MCMS\Core\Themes\DynamicMenuSourceInterface;
use MCMS\Modules\Catalogue\Services\Categories;

final class CategoryMenu implements DynamicMenuSourceInterface
{
    public function __construct(private readonly Categories $categories)
    {
    }

    public function id(): string
    {
        return 'catalogue.categories';
    }

    public function label(): string
    {
        return lang('Catalogue.categories');
    }

    public function tree(): array
    {
        return self::fromRows($this->categories->list(true));
    }

    public static function fromRows(array $categories): array
    {
        $children = [];
        foreach ($categories as $category) {
            $parent = $category['parent_id'] === null ? 0 : (int) $category['parent_id'];
            $children[$parent][] = $category;
        }
        $build = static function (int $parent, int $depth = 0) use (&$build, $children): array {
            // Depth guard in case corrupt external data creates a category cycle.
            if ($depth >= 16) {
                return [];
            }
            $tree = [];
            foreach ($children[$parent] ?? [] as $category) {
                $id = (int) $category['id'];
                $tree[] = [
                    'id' => $id,
                    'label' => (string) $category['name'],
                    'url' => (string) $category['url'],
                    'slug' => (string) $category['slug'],
                    'icon_type' => $category['icon_type'] ?? 'tabler',
                    'icon' => $category['icon'] ?? 'folder',
                    'icon_color' => $category['icon_color'] ?? '#206bc4',
                    'image_url' => $category['image_url'] ?? null,
                    'open_in_new' => false,
                    'children' => $build($id, $depth + 1),
                ];
            }
            return $tree;
        };
        return $build(0);
    }
}
