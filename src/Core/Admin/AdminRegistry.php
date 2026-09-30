<?php

declare(strict_types=1);

namespace MCMS\Core\Admin;

final class AdminRegistry
{
    private array $menu = [];
    private array $widgets = [];

    public function addMenu(
        string $id,
        string $label,
        string $url,
        string $icon = 'ti ti-point',
        ?string $permission = null,
        int $order = 100,
    ): void {
        $this->menu[$id] = compact('id', 'label', 'url', 'icon', 'permission', 'order');
    }

    public function addWidget(
        string $id,
        string $view,
        array $data = [],
        ?string $permission = null,
        int $order = 100,
    ): void {
        $this->widgets[$id] = compact('id', 'view', 'data', 'permission', 'order');
    }

    public function menu(): array
    {
        $items = array_values($this->menu);
        usort($items, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return $items;
    }

    public function widgets(): array
    {
        $items = array_values($this->widgets);
        usort($items, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return $items;
    }
}
