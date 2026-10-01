<?php

declare(strict_types=1);

namespace MCMS\Core\Themes;

use CodeIgniter\Settings\Settings;
use RuntimeException;

final class ThemeManager
{
    /** @var array<string, ThemeManifest> */
    private array $catalog = [];

    public function __construct(
        private readonly Settings $settings,
        private readonly string $themesPath = ROOTPATH . 'themes',
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

        return $value === '' ? null : $value;
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

    private function decorateViewData(array $data, ?ThemeManifest $theme): array
    {
        if (! array_key_exists('siteName', $data)) {
            $data['siteName'] = (string) ($this->settings->get('Core.siteName') ?? 'mCMS');
        }

        $data['theme'] = $theme;

        if ($theme !== null) {
            $data['themeAssets'] = site_url($theme->assetBaseUrl());
        }

        return $data;
    }

    private function resolveText(string $value): string
    {
        if ($value !== '' && str_starts_with($value, '@')) {
            return lang(substr($value, 1));
        }

        return $value;
    }
}