<?php

declare(strict_types=1);

namespace MCMS\Core\Themes;

use CodeIgniter\Settings\Settings;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ThemeManager
{
    /** @var array<string, ThemeManifest> */
    private array $catalog = [];

    public function __construct(
        private readonly Settings $settings,
        private readonly string $themesPath = ROOTPATH . 'themes',
        private readonly ?MenuSourceInterface $injectedMenuSource = null,
    ) {
    }

    /**
     * @return array<string, ThemeManifest>
     */
    public function catalog(): array
    {
        if ($this->catalog !== []) {
            return $this->catalog;
        }

        foreach (glob(rtrim($this->themesPath, DIRECTORY_SEPARATOR) . '/*/theme.json') ?: [] as $manifestPath) {
            $manifest = ThemeManifest::fromFile($manifestPath);

            if (isset($this->catalog[$manifest->id])) {
                throw new RuntimeException("Duplicate theme id: {$manifest->id}");
            }

            service('autoloader')->addNamespace(
                $manifest->namespace,
                $manifest->path,
            );

            $this->catalog[$manifest->id] = $manifest;
        }

        ksort($this->catalog);

        return $this->catalog;
    }

    public function find(string $id): ?ThemeManifest
    {
        $id = trim($id);

        return $id === '' ? null : ($this->catalog()[$id] ?? null);
    }

    public function activeId(): ?string
    {
        $value = trim((string) ($this->settings->get('Core.theme') ?? ''));
        if ($value !== '') {
            return $value;
        }

        return $this->find('mcms-default')?->id;
    }

    public function active(): ?ThemeManifest
    {
        $id = $this->activeId();
        if ($id === null) {
            return null;
        }

        return $this->find($id);
    }

    public function activate(string $id): ThemeManifest
    {
        $theme = $this->find($id);
        if ($theme === null) {
            throw new RuntimeException(lang('MCMS.errors.themeNotFound'));
        }

        $this->settings->set('Core.theme', $theme->id);

        return $theme;
    }

    public function render(string $logicalTemplate, array $data = [], ?string $fallbackView = null): string
    {
        $theme = $this->active();

        if ($theme !== null) {
            $view = $theme->view($logicalTemplate);
            if ($view !== null) {
                return view($view, $this->decorateViewData($data, $theme));
            }
        }

        if ($fallbackView !== null && trim($fallbackView) !== '') {
            return view($fallbackView, $this->decorateViewData($data, $theme));
        }

        throw new RuntimeException(
            sprintf(lang('MCMS.errors.themeTemplateMissing'), $logicalTemplate)
        );
    }

    public function assetUrl(string $path, ?ThemeManifest $theme = null): string
    {
        $theme ??= $this->active();

        if ($theme === null) {
            throw new RuntimeException(lang('MCMS.errors.themeNotActive'));
        }

        $path = ltrim(trim($path), '/');

        if ($path === ''
            || str_contains($path, '..')
            || str_contains($path, '\\')
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/', $path) !== 1) {
            throw new RuntimeException(lang('MCMS.errors.themeAssetPath'));
        }

        return site_url($theme->assetBaseUrl() . $path);
    }

    public function displayName(ThemeManifest $theme): string
    {
        return $this->resolveText($theme->name);
    }

    public function displayDescription(ThemeManifest $theme): string
    {
        return $this->resolveText($theme->description);
    }

    public function displayLocationLabel(array $location): string
    {
        return $this->resolveText((string) ($location['label'] ?? ''));
    }

    public function displayLocationDescription(array $location): string
    {
        return $this->resolveText((string) ($location['description'] ?? ''));
    }

    public function menusAvailable(): bool
    {
        return $this->menuSource() !== null;
    }

    public function availableMenus(): array
    {
        return $this->menuSource()?->list() ?? [];
    }

    /**
     * @return array<string, int|null>
     */
    public function menuAssignments(?ThemeManifest $theme = null): array
    {
        $theme ??= $this->active();
        if ($theme === null) {
            return [];
        }

        $all = $this->settings->get('Core.themeMenuAssignments');
        $stored = is_array($all) && isset($all[$theme->id]) && is_array($all[$theme->id])
            ? $all[$theme->id]
            : [];

        $result = [];
        foreach ($theme->menuLocations as $locationId => $_definition) {
            $value = $stored[$locationId] ?? null;
            $result[$locationId] = is_numeric($value) && (int) $value > 0
                ? (int) $value
                : null;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $assignments
     * @return array<string, int|null>
     */
    public function saveMenuAssignments(string $themeId, array $assignments): array
    {
        $theme = $this->find($themeId);
        if ($theme === null) {
            throw new RuntimeException(lang('MCMS.errors.themeNotFound'));
        }

        $source = $this->menuSource();
        $normalized = [];

        foreach ($theme->menuLocations as $locationId => $_definition) {
            $value = $assignments[$locationId] ?? null;
            if ($value === null || $value === '' || (int) $value === 0) {
                $normalized[$locationId] = null;
                continue;
            }

            if (! is_numeric($value) || (int) $value <= 0) {
                throw new InvalidArgumentException(lang('MCMS.errors.themeMenuInvalid'));
            }

            $menuId = (int) $value;
            if ($source === null) {
                throw new RuntimeException(lang('MCMS.errors.themeMenusUnavailable'));
            }

            if ($source->findMenu($menuId) === null) {
                throw new InvalidArgumentException(lang('MCMS.errors.themeMenuInvalid'));
            }

            $normalized[$locationId] = $menuId;
        }

        $all = $this->settings->get('Core.themeMenuAssignments');
        $all = is_array($all) ? $all : [];
        $all[$theme->id] = $normalized;
        $this->settings->set('Core.themeMenuAssignments', $all);

        return $normalized;
    }

    /**
     * @return array<string, array>
     */
    public function menuTrees(?ThemeManifest $theme = null): array
    {
        $theme ??= $this->active();
        if ($theme === null) {
            return [];
        }

        $source = $this->menuSource();
        $assignments = $this->menuAssignments($theme);
        $trees = [];

        foreach ($theme->menuLocations as $locationId => $definition) {
            $menuId = $assignments[$locationId] ?? null;
            if ($source === null || $menuId === null || $source->findMenu($menuId) === null) {
                $trees[$locationId] = [];
                continue;
            }

            $tree = $source->tree($menuId, true);
            $maxDepth = $definition['max_depth'] ?? null;
            $trees[$locationId] = $maxDepth === null
                ? $tree
                : $this->limitTreeDepth($tree, (int) $maxDepth);
        }

        return $trees;
    }

    private function decorateViewData(array $data, ?ThemeManifest $theme): array
    {
        if (! array_key_exists('siteName', $data)) {
            $data['siteName'] = (string) ($this->settings->get('Core.siteName') ?? 'mCMS');
        }

        $data['theme'] = $theme;

        if ($theme !== null) {
            $data['themeAssets'] = site_url($theme->assetBaseUrl());
            $data['themeMenus'] = $this->menuTrees($theme);
        } else {
            $data['themeMenus'] = [];
        }

        return $data;
    }

    private function menuSource(): ?MenuSourceInterface
    {
        if ($this->injectedMenuSource !== null) {
            return $this->injectedMenuSource;
        }

        try {
            $registry = service('mcmsModuleServices');
            if (! $registry->has('menus.repository')) {
                return null;
            }

            $source = $registry->get('menus.repository');

            return $source instanceof MenuSourceInterface ? $source : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function limitTreeDepth(array $items, int $maxDepth, int $depth = 1): array
    {
        foreach ($items as &$item) {
            $children = is_array($item['children'] ?? null) ? $item['children'] : [];
            $item['children'] = $depth >= $maxDepth
                ? []
                : $this->limitTreeDepth($children, $maxDepth, $depth + 1);
        }
        unset($item);

        return $items;
    }

    private function resolveText(string $value): string
    {
        if ($value !== '' && str_starts_with($value, '@')) {
            return lang(substr($value, 1));
        }

        return $value;
    }
}
