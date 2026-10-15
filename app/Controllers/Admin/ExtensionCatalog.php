<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

final class ExtensionCatalog extends BaseAdminController
{
    private function catalogGuard(): ?RedirectResponse
    {
        return $this->can('core.modules') || $this->can('core.themes') ? null : $this->guard('core.modules');
    }

    public function index(?string $id = null): string|RedirectResponse
    {
        if ($denied = $this->catalogGuard()) {
            return $denied;
        }
        $catalog = service('mcmsExtensionCatalog');
        $entries = array_filter($catalog->entries(), fn ($entry) => $this->can($entry['type'] === 'module' ? 'core.modules' : 'core.themes'));
        if ($id !== null && ! isset($entries[$id])) {
            return redirect()->to('/admin/extensions')->with('error', lang('Catalog.notFound'));
        }
        $type = $this->request->getGet('type') === 'module' && $this->can('core.modules') ? 'module' : ($this->can('core.themes') ? 'theme' : 'module');
        $query = mb_strtolower(mb_substr(trim((string) $this->request->getGet('q')), 0, 200));
        $tag = (string) $this->request->getGet('tag');
        $state = (string) $this->request->getGet('state');
        $tags = [];
        foreach ($entries as $entry) {
            if ($entry['type'] === $type) {
                $tags = [...$tags, ...$entry['tags']];
            }
        }
        $filtered = array_filter($entries, static fn ($entry) => $entry['type'] === $type
            && ($query === '' || str_contains(mb_strtolower($entry['name'] . ' ' . $entry['description']), $query))
            && ($tag === '' || in_array($tag, $entry['tags'], true)) && ($state === '' || $entry['state'] === $state));
        return view($id === null ? 'admin/catalog/index' : 'admin/catalog/show', [
            'adminMenu' => $this->adminMenu(), 'entries' => $filtered, 'entry' => $id === null ? null : $entries[$id],
            'catalogCache' => $catalog->cache(), 'catalogStatus' => $catalog->status(),
            'type' => $type, 'query' => $query, 'tag' => $tag, 'state' => $state, 'tags' => array_unique($tags),
        ]);
    }

    public function refresh(): RedirectResponse|ResponseInterface
    {
        if ($denied = $this->catalogGuard()) {
            return $denied;
        }
        // Automatic GET only reads the public catalog through its daily cache; it never applies packages.
        // It leaves session CSRF tokens intact while administrators edit other forms.
        $automatic = strtoupper($this->request->getMethod()) === 'GET';
        $error = null;
        try {
            service('mcmsExtensionCatalog')->refresh(! $automatic);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
        if ($automatic) {
            return $this->response->setHeader('Cache-Control', 'no-store')->setJSON([
                'error' => $error, 'updates' => ($this->can('core.modules') ? service('mcmsExtensionCatalog')->updateCount('module') : 0)
                    + ($this->can('core.themes') ? service('mcmsExtensionCatalog')->updateCount('theme') : 0),
            ]);
        }
        $type = $this->request->getPost('type') === 'module' ? 'module' : 'theme';
        return redirect()->to('/admin/extensions?type=' . $type)->with($error === null ? 'message' : 'error', $error ?? lang('Catalog.refreshed'));
    }

    public function prepare(string $id): RedirectResponse
    {
        if ($denied = $this->catalogGuard()) {
            return $denied;
        }
        try {
            $catalog = service('mcmsExtensionCatalog');
            $entry = $catalog->entries()[$id] ?? null;
            if ($entry === null) {
                throw new RuntimeException(lang('Catalog.notFound'));
            }
            if ($denied = $this->guard($entry['type'] === 'module' ? 'core.modules' : 'core.themes')) {
                return $denied;
            }
            $result = $catalog->prepare($id, (string) $this->request->getPost('version'));
            $type = $result['type'];
            $updating = $result['action'] === 'update';
            $key = $type . ($updating ? '_update_preview' : '_package_preview');
            $packages = service($type === 'module' ? 'mcmsModulePackages' : 'mcmsThemePackages');
            $old = session()->get($key);
            if (is_array($old) && ($old['owner'] ?? null) === auth()->id()) {
                $packages->cancel($old['token']);
            }
            $manifest = $result['manifest'];
            // The existing forms/controllers own preview confirmation and apply, including backup/recovery.
            session()->set($key, [
                'owner' => auth()->id(), 'id' => $id, 'token' => $result['token'], 'sha256' => $result['sha256'],
                'name' => $manifest->name, 'description' => $manifest->description, 'version' => $manifest->version,
                'mcms' => $manifest->mcms, 'requires' => $manifest->requires ?? [],
                'files' => count($result['files']), 'bytes' => $result['bytes'],
                'templates' => isset($manifest->templates) ? count($manifest->templates) : 0,
                'locations' => isset($manifest->menuLocations) ? count($manifest->menuLocations) : 0,
                'source' => $result['source'] ?? null, 'current' => $result['current'] ?? null,
            ]);
            return redirect()->to('/admin/' . $type . 's/' . ($updating ? rawurlencode($id) . '/update' : 'package'));
        } catch (Throwable $e) {
            return redirect()->to('/admin/extensions/' . rawurlencode($id))->with('error', $e->getMessage());
        }
    }
}
