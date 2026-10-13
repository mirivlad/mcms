<?php

declare(strict_types=1);

namespace MCMS\Core\Themes;

use CodeIgniter\Settings\Settings;
use Composer\Semver\Semver;
use MCMS\Core\Version;
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
        private readonly ?string $packagesPath = null,
    ) {
    }

    /**
     * @return array<string, ThemeManifest>
     */
    public function catalog(): array
    {
        if ($this->catalog !== []) {
            // Test/service resets may replace the namespace loader while this
            // ThemeManager instance retains its catalog. Re-register paths.
            foreach ($this->catalog as $manifest) {
                service('autoloader')->addNamespace($manifest->namespace, $manifest->path);
            }
            return $this->catalog;
        }

        $paths = glob(rtrim($this->themesPath, DIRECTORY_SEPARATOR) . '/*/theme.json') ?: [];
        $externalPaths = $this->packagesPath === null ? [] : (glob($this->packagesPath . '/installed/*/theme.json') ?: []);
        $catalog = [];
        foreach ([...$paths, ...$externalPaths] as $manifestPath) {
            $manifest = ThemeManifest::fromFile($manifestPath, in_array($manifestPath, $externalPaths, true));
            if (isset($catalog[$manifest->id])) {
                throw new RuntimeException("Duplicate theme id: {$manifest->id}");
            }
            foreach ($catalog as $current) {
                if (strcasecmp($current->namespace, $manifest->namespace) === 0) {
                    throw new RuntimeException("Duplicate theme namespace: {$manifest->namespace}");
                }
            }
            $catalog[$manifest->id] = $manifest;
        }
        foreach ($catalog as $manifest) {
            service('autoloader')->addNamespace($manifest->namespace, $manifest->path);
        }
        $this->catalog = $catalog;

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

        if (! Semver::satisfies(Version::VERSION, $theme->mcms)) {
            throw new RuntimeException('Theme requires mCMS ' . $theme->mcms . '.');
        }
        $this->settings->set('Core.theme', $theme->id);

        return $theme;
    }

    public function render(string $logicalTemplate, array $data = [], ?string $fallbackView = null): string
    {
        $data['isThemeHome'] = $logicalTemplate === 'home';
        foreach (service('mcmsModuleServices')->all() as $provider) {
            if ($provider instanceof PageMetadataProviderInterface) {
                $data = array_replace($data, $provider->metadata($logicalTemplate, $data));
            }
        }
        $theme = $this->active();

        if ($theme !== null) {
            $view = $theme->view($logicalTemplate);
            if ($view !== null) {
                return view($view, $this->decorateViewData($data, $theme, $logicalTemplate));
            }
        }

        if ($fallbackView !== null && trim($fallbackView) !== '') {
            return view($fallbackView, $this->decorateViewData($data, $theme, $logicalTemplate));
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

    public function appearance(?ThemeManifest $theme = null): array
    {
        $theme ??= $this->active();
        $all = $this->settings->get('Core.themeAppearance');
        $stored = $theme !== null && is_array($all) ? ($all[$theme->id] ?? []) : [];
        $values = array_replace([
            'logo_media_id' => null, 'favicon_media_id' => null, 'accent' => '',
            'phone' => '', 'email' => '', 'address' => '',
        ], is_array($stored) ? $stored : []);
        $source = $this->imageSource();
        $values['logo'] = $source?->findImage((int) $values['logo_media_id']);
        $values['favicon'] = $source?->findImage((int) $values['favicon_media_id']);
        return $values;
    }

    public function saveAppearance(string $themeId, array $input): void
    {
        $theme = $this->find($themeId);
        if ($theme === null) {
            throw new RuntimeException(lang('MCMS.errors.themeNotFound'));
        }
        $current = $this->appearance($theme);
        $values = [];
        foreach (['logo_media_id', 'favicon_media_id'] as $key) {
            $value = $input[$key] ?? null;
            $id = $value === null || $value === '' || $value === '0' || $value === 0 ? null : filter_var($value, FILTER_VALIDATE_INT);
            if ($id !== null && ($id === false || $id <= 0
                || ($this->imageSource() === null ? $id !== $current[$key] : $this->imageSource()->findImage($id) === null))) {
                throw new InvalidArgumentException(lang('MCMS.themes.invalidImage'));
            }
            $values[$key] = $id;
        }
        $accent = strtolower(trim((string) ($input['accent'] ?? '')));
        if ($accent !== '' && preg_match('/^#[0-9a-f]{6}$/', $accent) !== 1) {
            throw new InvalidArgumentException(lang('MCMS.themes.invalidColor'));
        }
        $values['accent'] = $accent;
        $values['phone'] = mb_substr(trim((string) ($input['phone'] ?? '')), 0, 80);
        $values['email'] = trim((string) ($input['email'] ?? ''));
        $values['address'] = mb_substr(trim((string) ($input['address'] ?? '')), 0, 500);
        if ($values['email'] !== '' && filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(lang('MCMS.themes.invalidEmail'));
        }
        $all = $this->settings->get('Core.themeAppearance');
        $all = is_array($all) ? $all : [];
        $all[$themeId] = $values;
        $this->settings->set('Core.themeAppearance', $all);
    }

    public function images(string $query = '', int $offset = 0): array
    {
        return $this->imageSource()?->images($query, max(0, $offset)) ?? ['items' => [], 'count' => 0];
    }

    public function homeContent(?ThemeManifest $theme = null): array
    {
        $theme ??= $this->active();
        $defaults = [
            'eyebrow' => '', 'title' => '', 'description' => '', 'button_label' => '',
            'button_url' => '#home-content', 'intro_title' => '', 'intro_text' => '',
            'image_media_id' => null, 'show_news' => true, 'show_galleries' => true,
        ];
        foreach ($theme?->homeDefaults ?? [] as $key => $value) {
            $defaults[$key] = is_string($value) ? $this->resolveText($value) : $value;
        }
        $all = $this->settings->get('Core.themeHome');
        $stored = $theme !== null && is_array($all) ? ($all[$theme->id] ?? []) : [];
        $values = array_replace($defaults, is_array($stored) ? array_intersect_key($stored, $defaults) : []);
        $values['image'] = $this->imageSource()?->findImage((int) $values['image_media_id']);
        return $values;
    }

    public function saveHomeContent(string $themeId, array $input): void
    {
        $theme = $this->find($themeId);
        if ($theme === null || $theme->homeDefaults === []) {
            throw new InvalidArgumentException(lang('MCMS.themes.homeUnavailable'));
        }
        $values = [];
        foreach (['eyebrow' => 120, 'title' => 180, 'description' => 600, 'button_label' => 80,
            'button_url' => 1000, 'intro_title' => 180, 'intro_text' => 600] as $key => $limit) {
            if (isset($input[$key]) && ! is_string($input[$key])) {
                throw new InvalidArgumentException(lang('MCMS.themes.invalidHome'));
            }
            $values[$key] = trim((string) ($input[$key] ?? ''));
            if (mb_strlen($values[$key]) > $limit) {
                throw new InvalidArgumentException(lang('MCMS.themes.invalidHome'));
            }
        }
        if ($values['title'] === '') {
            throw new InvalidArgumentException(lang('MCMS.themes.homeTitleRequired'));
        }
        $url = $values['button_url'];
        if ($url !== '' && (preg_match('/[\x00-\x20\x7f\\\\]/', $url)
            || ! ((str_starts_with($url, '/') && ! str_starts_with($url, '//'))
                || preg_match('/^#[A-Za-z][A-Za-z0-9_.:-]*$/', $url)
                || (preg_match('/^https?:\/\//i', $url) && filter_var($url, FILTER_VALIDATE_URL))))) {
            throw new InvalidArgumentException(lang('MCMS.themes.invalidHomeUrl'));
        }
        $current = $this->homeContent($theme);
        $rawId = $input['image_media_id'] ?? null;
        $id = $rawId === null || $rawId === '' || $rawId === '0' || $rawId === 0 ? null : filter_var($rawId, FILTER_VALIDATE_INT);
        $source = $this->imageSource();
        if ($id !== null && ($id === false || $id <= 0
            || ($source === null ? $id !== $current['image_media_id'] : $source->findImage($id) === null))) {
            throw new InvalidArgumentException(lang('MCMS.themes.invalidImage'));
        }
        $values['image_media_id'] = $id;
        foreach (['show_news', 'show_galleries'] as $key) {
            $values[$key] = in_array($input[$key] ?? null, [true, 1, '1'], true);
        }
        $all = $this->settings->get('Core.themeHome');
        $all = is_array($all) ? $all : [];
        $all[$themeId] = $values;
        $this->settings->set('Core.themeHome', $all);
    }

    public function imagesAvailable(): bool
    {
        return $this->imageSource() !== null;
    }

    private function imageSource(): ?ThemeImageSourceInterface
    {
        foreach (service('mcmsModuleServices')->all() as $provider) {
            if ($provider instanceof ThemeImageSourceInterface) {
                return $provider;
            }
        }
        return null;
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
     * Optional generated menus and secondary Menus trees.
     * Entries are specific to a theme location, without changing the existing primary assignment.
     *
     * @return array<string, list<array{source:string,visibility:string}>>
     */
    public function extraMenuAssignments(?ThemeManifest $theme = null): array
    {
        $theme ??= $this->active();
        if ($theme === null) {
            return [];
        }
        $all = $this->settings->get('Core.themeExtraMenus');
        $stored = is_array($all) && is_array($all[$theme->id] ?? null) ? $all[$theme->id] : [];
        $result = [];
        foreach ($theme->menuLocations as $locationId => $_definition) {
            $result[$locationId] = [];
            foreach (($stored[$locationId] ?? []) as $assignment) {
                if (is_array($assignment)
                    && is_string($assignment['source'] ?? null)
                    && is_string($assignment['visibility'] ?? null)) {
                    $result[$locationId][] = $assignment;
                }
            }
        }
        return $result;
    }

    /** @return array<string, string> Human-readable source labels indexed by stable source ID. */
    public function availableExtraMenuSources(): array
    {
        $choices = [];
        foreach ($this->availableMenus() as $menu) {
            $choices['menu:' . $menu['id']] = $menu['name'];
        }
        foreach ($this->dynamicMenuSources() as $id => $source) {
            $choices['dynamic:' . $id] = $source->label();
        }
        return $choices;
    }

    /** @param array<string, list<array{source:string,visibility:string}>> $input */
    public function saveExtraMenuAssignments(string $themeId, array $input): void
    {
        $theme = $this->find($themeId);
        if ($theme === null) {
            throw new RuntimeException(lang('MCMS.errors.themeNotFound'));
        }
        $available = $this->availableExtraMenuSources();
        $existing = $this->extraMenuAssignments($theme);
        $normalized = [];
        $visibilityOptions = ['all', 'shop', 'pages', 'news', 'products'];
        foreach ($theme->menuLocations as $locationId => $_definition) {
            $normalized[$locationId] = [];
            $seen = [];
            $rows = $input[$locationId] ?? [];
            if (! is_array($rows) || count($rows) > 3) {
                throw new InvalidArgumentException(lang('MCMS.errors.themeMenuInvalid'));
            }
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    throw new InvalidArgumentException(lang('MCMS.errors.themeMenuInvalid'));
                }
                $source = trim((string) ($row['source'] ?? ''));
                if ($source === '') {
                    continue;
                }
                $visibility = (string) ($row['visibility'] ?? 'all');
                if (! in_array($visibility, $visibilityOptions, true)
                    || ! preg_match('/^(menu:[1-9][0-9]*|dynamic:[a-z][a-z0-9._-]*)$/D', $source)
                    || isset($seen[$source])) {
                    throw new InvalidArgumentException(lang('MCMS.errors.themeMenuInvalid'));
                }
                // Preserve references to temporarily disabled providers, but do not
                // allow submitting an arbitrary inaccessible source.
                $wasSaved = in_array($source, array_column($existing[$locationId] ?? [], 'source'), true);
                if (! isset($available[$source]) && ! $wasSaved) {
                    throw new InvalidArgumentException(lang('MCMS.errors.themeMenuInvalid'));
                }
                $seen[$source] = true;
                $normalized[$locationId][] = ['source' => $source, 'visibility' => $visibility];
            }
        }
        $all = $this->settings->get('Core.themeExtraMenus');
        $all = is_array($all) ? $all : [];
        $all[$themeId] = $normalized;
        $this->settings->set('Core.themeExtraMenus', $all);
    }

    /** @return array<string, DynamicMenuSourceInterface> */
    private function dynamicMenuSources(): array
    {
        $found = [];
        foreach (service('mcmsModuleServices')->all() as $service) {
            if ($service instanceof DynamicMenuSourceInterface) {
                $found[$service->id()] = $service;
            }
        }
        return $found;
    }

    private static function visibleExtraMenu(string $scope, ?string $logicalTemplate): bool
    {
        return match ($scope) {
            'all' => true,
            'shop' => str_starts_with((string) $logicalTemplate, 'catalogue.'),
            'pages' => $logicalTemplate === 'page',
            'news' => str_starts_with((string) $logicalTemplate, 'news.'),
            'products' => $logicalTemplate === 'catalogue.product',
            default => false,
        };
    }

    /**
     * @return array<string, array>
     */
    public function menuTrees(?ThemeManifest $theme = null, ?string $logicalTemplate = null): array
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

        // Append optional, independently scoped sources after the primary menu.
        // This preserves the old themeMenus shape expected by existing themes.
        $dynamic = $this->dynamicMenuSources();
        foreach ($this->extraMenuAssignments($theme) as $locationId => $entries) {
            $depth = $theme->menuLocations[$locationId]['max_depth'] ?? null;
            foreach ($entries as $entry) {
                if (! self::visibleExtraMenu($entry['visibility'], $logicalTemplate)) {
                    continue;
                }
                $ref = $entry['source'];
                $items = [];
                if (str_starts_with($ref, 'menu:') && $source !== null) {
                    $id = (int) substr($ref, 5);
                    if ($id > 0 && $source->findMenu($id) !== null) {
                        $items = $source->tree($id, true);
                    }
                } elseif (str_starts_with($ref, 'dynamic:')) {
                    $items = ($dynamic[substr($ref, 8)] ?? null)?->tree() ?? [];
                }
                if ($depth !== null) {
                    $items = $this->limitTreeDepth($items, (int) $depth);
                }
                $trees[$locationId] = array_merge($trees[$locationId] ?? [], $items);
            }
        }

        return $trees;
    }

    private function decorateViewData(array $data, ?ThemeManifest $theme, string $logicalTemplate): array
    {
        if (! array_key_exists('siteName', $data)) {
            $data['siteName'] = (string) ($this->settings->get('Core.siteName') ?? 'mCMS');
        }

        $data['theme'] = $theme;
        if (str_starts_with($logicalTemplate, 'account.')) {
            // Core account views reuse the active theme's header, navigation
            // and footer, while optional account.* theme templates may replace
            // individual account screens via the normal manifest API.
            $data['accountThemeLayout'] = $theme !== null
                && is_file($theme->path . '/Views/layout.php')
                ? $theme->namespace . '\Views\layout'
                : 'auth/standalone_layout';
        }
        $data['themeAppearance'] = $this->appearance($theme);
        if ($theme !== null && $theme->homeDefaults !== []) {
            $data['themeHome'] = $this->homeContent($theme);
        }

        if ($theme !== null) {
            $data['themeAssets'] = site_url($theme->assetBaseUrl());
            $data['themeMenus'] = $this->menuTrees($theme, $logicalTemplate);
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
