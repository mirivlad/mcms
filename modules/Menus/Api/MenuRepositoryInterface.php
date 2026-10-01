<?php

declare(strict_types=1);

namespace MCMS\Modules\Menus\Api;

interface MenuRepositoryInterface
{
    public function list(): array;

    public function findMenu(int $id): ?array;

    public function findByHandle(string $handle): ?array;

    public function createMenu(array $data): array;

    public function updateMenu(int $id, array $data): array;

    public function deleteMenu(int $id): void;

    public function items(int $menuId, bool $enabledOnly = false): array;

    public function tree(int $menuId, bool $enabledOnly = false): array;

    public function treeByHandle(string $handle, bool $enabledOnly = true): array;

    public function findItem(int $menuId, int $itemId): ?array;

    public function saveItem(int $menuId, ?int $itemId, array $data): array;

    public function deleteItem(int $menuId, int $itemId): void;

    /**
     * @param list<array{id:int,parent_id:int|null,position:int}> $entries
     */
    public function updateStructure(int $menuId, array $entries): void;
}