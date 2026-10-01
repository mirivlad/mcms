<?php

declare(strict_types=1);

namespace MCMS\Core\Themes;

interface MenuSourceInterface
{
    public function list(): array;

    public function findMenu(int $id): ?array;

    public function tree(int $menuId, bool $enabledOnly = false): array;
}
