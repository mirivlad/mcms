<?php

declare(strict_types=1);

namespace MCMS\Modules\Menus\Services;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use MCMS\Modules\Menus\Api\MenuRepositoryInterface;
use RuntimeException;

final class MenuRepository implements MenuRepositoryInterface
{
    private const TARGET_TYPES = ['custom'];

    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function list(): array
    {
        $rows = $this->db->table('menus')
            ->orderBy('name', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $counts = [];
        foreach (
            $this->db->table('menu_items')
                ->select('menu_id, COUNT(*) AS item_count')
                ->groupBy('menu_id')
                ->get()
                ->getResultArray() as $row
        ) {
            $counts[(int) $row['menu_id']] = (int) $row['item_count'];
        }

        return array_map(
            function (array $row) use ($counts): array {
                $menu = $this->decorateMenu($row);
                $menu['item_count'] = $counts[$menu['id']] ?? 0;

                return $menu;
            },
            $rows,
        );
    }

    public function findMenu(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = $this->db->table('menus')->where('id', $id)->get()->getRowArray();

        return $row === null ? null : $this->decorateMenu($row);
    }

    public function findByHandle(string $handle): ?array
    {
        $handle = $this->normalizeHandle($handle, false);
        if ($handle === '') {
            return null;
        }

        $row = $this->db->table('menus')->where('handle', $handle)->get()->getRowArray();

        return $row === null ? null : $this->decorateMenu($row);
    }

    public function createMenu(array $data): array
    {
        $normalized = $this->normalizeMenu($data, null);
        $now = date('Y-m-d H:i:s');

        $this->db->table('menus')->insert([
            ...$normalized,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $menu = $this->findMenu((int) $this->db->insertID());
        if ($menu === null) {
            throw new RuntimeException(lang('Menus.Errors.menuNotFound'));
        }

        return $menu;
    }

    public function updateMenu(int $id, array $data): array
    {
        if ($this->findMenu($id) === null) {
            throw new RuntimeException(lang('Menus.Errors.menuNotFound'));
        }

        $normalized = $this->normalizeMenu($data, $id);

        $this->db->table('menus')
            ->where('id', $id)
            ->update([
                ...$normalized,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        $menu = $this->findMenu($id);
        if ($menu === null) {
            throw new RuntimeException(lang('Menus.Errors.menuNotFound'));
        }

        return $menu;
    }

    public function deleteMenu(int $id): void
    {
        if ($this->findMenu($id) === null) {
            return;
        }

        $this->db->transStart();
        $this->db->table('menu_items')->where('menu_id', $id)->delete();
        $this->db->table('menus')->where('id', $id)->delete();
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new RuntimeException(lang('Menus.Errors.saveFailed'));
        }
    }

    public function items(int $menuId, bool $enabledOnly = false): array
    {
        if ($this->findMenu($menuId) === null) {
            return [];
        }

        $builder = $this->db->table('menu_items')->where('menu_id', $menuId);
        if ($enabledOnly) {
            $builder->where('enabled', 1);
        }

        $rows = $builder
            ->orderBy('position', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(fn (array $row): array => $this->decorateItem($row), $rows);
    }

    public function tree(int $menuId, bool $enabledOnly = false): array
    {
        $items = $this->items($menuId, $enabledOnly);
        $children = [];

        foreach ($items as $item) {
            $parent = $item['parent_id'] ?? 0;
            $children[$parent][] = $item;
        }

        $build = function (?int $parentId) use (&$build, $children): array {
            $key = $parentId ?? 0;
            $result = [];

            foreach ($children[$key] ?? [] as $item) {
                $item['children'] = $build($item['id']);
                $result[] = $item;
            }

            return $result;
        };

        return $build(null);
    }

    public function treeByHandle(string $handle, bool $enabledOnly = true): array
    {
        $menu = $this->findByHandle($handle);

        return $menu === null ? [] : $this->tree($menu['id'], $enabledOnly);
    }

    public function findItem(int $menuId, int $itemId): ?array
    {
        if ($menuId <= 0 || $itemId <= 0) {
            return null;
        }

        $row = $this->db->table('menu_items')
            ->where('menu_id', $menuId)
            ->where('id', $itemId)
            ->get()
            ->getRowArray();

        return $row === null ? null : $this->decorateItem($row);
    }

    public function saveItem(int $menuId, ?int $itemId, array $data): array
    {
        if ($this->findMenu($menuId) === null) {
            throw new RuntimeException(lang('Menus.Errors.menuNotFound'));
        }

        $current = $itemId === null ? null : $this->findItem($menuId, $itemId);
        if ($itemId !== null && $current === null) {
            throw new RuntimeException(lang('Menus.Errors.itemNotFound'));
        }

        $parentId = $this->normalizeParentId($menuId, $itemId, $data['parent_id'] ?? null);
        $normalized = $this->normalizeItem($data);
        $now = date('Y-m-d H:i:s');

        if ($current === null) {
            $this->db->table('menu_items')->insert([
                'menu_id' => $menuId,
                'parent_id' => $parentId,
                ...$normalized,
                'position' => $this->nextPosition($menuId, $parentId),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $itemId = (int) $this->db->insertID();
        } else {
            $position = $current['parent_id'] === $parentId
                ? (int) $current['position']
                : $this->nextPosition($menuId, $parentId);

            $this->db->table('menu_items')
                ->where('menu_id', $menuId)
                ->where('id', $itemId)
                ->update([
                    'parent_id' => $parentId,
                    ...$normalized,
                    'position' => $position,
                    'updated_at' => $now,
                ]);
        }

        $item = $this->findItem($menuId, (int) $itemId);
        if ($item === null) {
            throw new RuntimeException(lang('Menus.Errors.itemNotFound'));
        }

        return $item;
    }

    public function deleteItem(int $menuId, int $itemId): void
    {
        if ($this->findItem($menuId, $itemId) === null) {
            return;
        }

        $children = [];
        foreach ($this->items($menuId) as $item) {
            $parent = $item['parent_id'] ?? 0;
            $children[$parent][] = $item['id'];
        }

        $deleteIds = [];
        $queue = [$itemId];

        while ($queue !== []) {
            $id = array_shift($queue);
            if (in_array($id, $deleteIds, true)) {
                continue;
            }

            $deleteIds[] = $id;
            foreach ($children[$id] ?? [] as $childId) {
                $queue[] = $childId;
            }
        }

        $this->db->table('menu_items')
            ->where('menu_id', $menuId)
            ->whereIn('id', $deleteIds)
            ->delete();
    }

    public function updateStructure(int $menuId, array $entries): void
    {
        if ($this->findMenu($menuId) === null) {
            throw new RuntimeException(lang('Menus.Errors.menuNotFound'));
        }

        $items = $this->items($menuId);
        $itemIds = array_map(static fn (array $item): int => $item['id'], $items);
        sort($itemIds);

        $normalized = [];
        $seen = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                throw new InvalidArgumentException(lang('Menus.Errors.invalidStructure'));
            }

            $id = (int) ($entry['id'] ?? 0);
            if ($id <= 0 || isset($seen[$id])) {
                throw new InvalidArgumentException(lang('Menus.Errors.invalidStructure'));
            }

            $parent = $entry['parent_id'] ?? null;
            $parent = $parent === null || $parent === '' || (int) $parent === 0
                ? null
                : (int) $parent;

            $position = max(0, (int) ($entry['position'] ?? 0));
            $normalized[$id] = [
                'id' => $id,
                'parent_id' => $parent,
                'position' => $position,
            ];
            $seen[$id] = true;
        }

        $submittedIds = array_keys($normalized);
        sort($submittedIds);

        if ($submittedIds !== $itemIds) {
            throw new InvalidArgumentException(lang('Menus.Errors.invalidStructure'));
        }

        foreach ($normalized as $id => $entry) {
            $parent = $entry['parent_id'];
            if ($parent !== null && ($parent === $id || ! isset($normalized[$parent]))) {
                throw new InvalidArgumentException(lang('Menus.Errors.invalidStructure'));
            }
        }

        foreach (array_keys($normalized) as $id) {
            $visited = [];
            $cursor = $id;

            while ($cursor !== null) {
                if (isset($visited[$cursor])) {
                    throw new InvalidArgumentException(lang('Menus.Errors.circularStructure'));
                }

                $visited[$cursor] = true;
                $cursor = $normalized[$cursor]['parent_id'] ?? null;
            }
        }

        $this->db->transStart();
        foreach ($normalized as $entry) {
            $this->db->table('menu_items')
                ->where('menu_id', $menuId)
                ->where('id', $entry['id'])
                ->update([
                    'parent_id' => $entry['parent_id'],
                    'position' => $entry['position'],
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new RuntimeException(lang('Menus.Errors.saveFailed'));
        }
    }

    private function normalizeMenu(array $data, ?int $currentId): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException(lang('Menus.Errors.nameRequired'));
        }

        $name = mb_substr($name, 0, 120);
        $handleInput = trim((string) ($data['handle'] ?? ''));
        $handle = $handleInput === ''
            ? $this->slugify($name)
            : $this->normalizeHandle($handleInput, true);

        if ($handle === '') {
            throw new InvalidArgumentException(lang('Menus.Errors.invalidHandle'));
        }

        $handle = $this->uniqueHandle($handle, $currentId);
        $description = trim((string) ($data['description'] ?? ''));

        return [
            'name' => $name,
            'handle' => $handle,
            'description' => $description === '' ? null : mb_substr($description, 0, 255),
        ];
    }

    private function normalizeItem(array $data): array
    {
        $label = trim((string) ($data['label'] ?? ''));
        if ($label === '') {
            throw new InvalidArgumentException(lang('Menus.Errors.labelRequired'));
        }

        $type = trim((string) ($data['target_type'] ?? 'custom'));
        if (! in_array($type, self::TARGET_TYPES, true)) {
            throw new InvalidArgumentException(lang('Menus.Errors.invalidTargetType'));
        }

        $target = trim((string) ($data['target'] ?? ''));
        if ($target !== '' && ! $this->isValidCustomTarget($target)) {
            throw new InvalidArgumentException(lang('Menus.Errors.invalidTarget'));
        }

        return [
            'label' => mb_substr($label, 0, 190),
            'target_type' => $type,
            'target' => $target === '' ? null : mb_substr($target, 0, 500),
            'enabled' => $this->toBool($data['enabled'] ?? false) ? 1 : 0,
            'open_in_new' => $this->toBool($data['open_in_new'] ?? false) ? 1 : 0,
        ];
    }

    private function normalizeParentId(int $menuId, ?int $itemId, mixed $value): ?int
    {
        $parentId = $value === null || $value === '' || (int) $value === 0
            ? null
            : (int) $value;

        if ($parentId === null) {
            return null;
        }

        if ($itemId !== null && $parentId === $itemId) {
            throw new InvalidArgumentException(lang('Menus.Errors.circularStructure'));
        }

        if ($this->findItem($menuId, $parentId) === null) {
            throw new InvalidArgumentException(lang('Menus.Errors.invalidParent'));
        }

        if ($itemId !== null) {
            $cursor = $parentId;
            $seen = [];

            while ($cursor !== null) {
                if ($cursor === $itemId || isset($seen[$cursor])) {
                    throw new InvalidArgumentException(lang('Menus.Errors.circularStructure'));
                }

                $seen[$cursor] = true;
                $parent = $this->findItem($menuId, $cursor);
                $cursor = $parent['parent_id'] ?? null;
            }
        }

        return $parentId;
    }

    private function nextPosition(int $menuId, ?int $parentId): int
    {
        $builder = $this->db->table('menu_items')
            ->selectMax('position', 'max_position')
            ->where('menu_id', $menuId);

        if ($parentId === null) {
            $builder->where('parent_id', null);
        } else {
            $builder->where('parent_id', $parentId);
        }

        $row = $builder->get()->getRowArray();

        return ((int) ($row['max_position'] ?? 0)) + 10;
    }

    private function uniqueHandle(string $base, ?int $currentId): string
    {
        $handle = $base;
        $suffix = 2;

        while ($this->handleExists($handle, $currentId)) {
            $ending = '-' . $suffix++;
            $handle = mb_substr($base, 0, 100 - strlen($ending)) . $ending;

            if ($suffix > 10000) {
                throw new RuntimeException(lang('Menus.Errors.duplicateHandle'));
            }
        }

        return $handle;
    }

    private function handleExists(string $handle, ?int $currentId): bool
    {
        $builder = $this->db->table('menus')->where('handle', $handle);

        if ($currentId !== null) {
            $builder->where('id !=', $currentId);
        }

        return $builder->countAllResults() > 0;
    }

    private function normalizeHandle(string $handle, bool $strict): string
    {
        $handle = strtolower(trim($handle));
        $handle = trim($handle, '-');

        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $handle) !== 1) {
            if ($strict) {
                throw new InvalidArgumentException(lang('Menus.Errors.invalidHandle'));
            }

            return '';
        }

        return mb_substr($handle, 0, 100);
    }

    private function slugify(string $value): string
    {
        $map = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y',
            'к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f',
            'х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
        ];

        $value = strtr(mb_strtolower($value), $map);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return $this->normalizeHandle(trim($value, '-'), false);
    }

    private function isValidCustomTarget(string $target): bool
    {
        if ($target === '#'
            || str_starts_with($target, '#')
            || str_starts_with($target, '?')) {
            return true;
        }

        if (str_starts_with($target, '/') && ! str_starts_with($target, '//')) {
            return true;
        }

        return preg_match('/^(https?:\/\/|mailto:|tel:)/i', $target) === 1;
    }

    private function toBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function decorateMenu(array $row): array
    {
        $row['id'] = (int) $row['id'];

        return $row;
    }

    private function decorateItem(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['menu_id'] = (int) $row['menu_id'];
        $row['parent_id'] = $row['parent_id'] === null ? null : (int) $row['parent_id'];
        $row['position'] = (int) $row['position'];
        $row['enabled'] = (bool) $row['enabled'];
        $row['open_in_new'] = (bool) $row['open_in_new'];
        $row['url'] = $row['target_type'] === 'custom' ? $row['target'] : null;

        return $row;
    }
}