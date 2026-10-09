<?php

declare(strict_types=1);

namespace MCMS\Core\Admin;

final class AdminRegistry
{
    private const MENU_GROUPS = [
        'main' => ['', ''],
        'content' => ['@MCMS.nav.groupContent', 'ti ti-files'],
        'appearance' => ['@MCMS.nav.groupAppearance', 'ti ti-palette'],
        'commerce' => ['@MCMS.nav.groupCommerce', 'ti ti-building-store'],
        'administration' => ['@MCMS.nav.groupAdministration', 'ti ti-settings'],
        'extensions' => ['@MCMS.nav.groupExtensions', 'ti ti-puzzle'],
    ];

    private array $menu = [];
    private array $widgets = [];
    private array $settings = [];

    public function addMenu(
        string $id,
        string $label,
        string $url,
        string $icon = 'ti ti-point',
        ?string $permission = null,
        int $order = 100,
        string $group = 'extensions',
    ): void {
        $this->menu[$id] = compact('id', 'label', 'url', 'icon', 'permission', 'order', 'group');
    }

    /** Module-owned forms keep their original route and add a central route. */
    public function addSettings(string $id, string $label, string $url, string $icon, string $permission, string $group): void
    {
        if (preg_match('/^[a-z][a-z0-9-]*$/D', $id) !== 1 || ! str_starts_with($url, '/admin/')) {
            throw new \InvalidArgumentException('Invalid settings section.');
        }
        $centralUrl = $id === 'general' ? '/admin/settings' : '/admin/settings/' . $id;
        $this->settings[$id] = compact('id', 'label', 'url', 'centralUrl', 'icon', 'permission', 'group');
    }

    public function settings(): array
    {
        return array_values($this->settings);
    }

    /** Preserve central navigation for form actions and post-save redirects. */
    public function settingsPath(string $path, string $requestPath): string
    {
        $requestPath = '/' . trim($requestPath, '/');
        foreach ($this->settings as $section) {
            if ($section['id'] !== 'general' && ($requestPath === $section['centralUrl'] || str_starts_with($requestPath, $section['centralUrl'] . '/'))
                && ($path === $section['url'] || str_starts_with($path, $section['url'] . '/')
                    || str_starts_with($path, $section['url'] . '?') || str_starts_with($path, $section['url'] . '#'))) {
                return $section['centralUrl'] . substr($path, strlen($section['url']));
            }
        }
        return $path;
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

        foreach ($items as &$item) {
            if (is_string($item['label']) && str_starts_with($item['label'], '@')) {
                $item['label'] = lang(substr($item['label'], 1));
            }
        }
        unset($item);

        usort($items, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return $items;
    }

    public function widgets(): array
    {
        $items = array_values($this->widgets);
        usort($items, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return $items;
    }

    /** Group already permission-filtered menu items, omitting empty sections. */
    public function groupedMenu(array $items): array
    {
        $groups = [];
        foreach (self::MENU_GROUPS as $id => [$label, $icon]) {
            $groups[$id] = compact('id', 'label', 'icon') + ['items' => []];
        }
        foreach ($items as $item) {
            $id = $item['group'] ?? 'extensions';
            $groups[isset($groups[$id]) ? $id : 'extensions']['items'][] = $item;
        }

        return array_values(array_filter($groups, static fn (array $group): bool => $group['items'] !== []));
    }
}
