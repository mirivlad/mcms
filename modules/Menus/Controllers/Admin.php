<?php

declare(strict_types=1);

namespace MCMS\Modules\Menus\Controllers;

use App\Controllers\Admin\BaseAdminController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use JsonException;
use MCMS\Modules\Menus\Api\MenuRepositoryInterface;
use RuntimeException;

final class Admin extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if (($redirect = $this->guard('menus.view')) !== null) {
            return $redirect;
        }

        return view('MCMS\\Modules\\Menus\\Views\\index', [
            'title' => lang('Menus.Admin.title'),
            'adminMenu' => $this->adminMenu(),
            'user' => auth()->user(),
            'menus' => $this->menus()->list(),
            'canManage' => $this->can('menus.manage'),
        ]);
    }

    public function create(): string|RedirectResponse
    {
        if (($redirect = $this->guard('menus.manage')) !== null) {
            return $redirect;
        }

        if ($this->request->getMethod() === 'POST') {
            try {
                $menu = $this->menus()->createMenu($this->menuData());

                return redirect()->to('/admin/menus/' . $menu['id'] . '/edit')
                    ->with('message', lang('Menus.Messages.created'));
            } catch (\Throwable $e) {
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }
        }

        return $this->form(null);
    }

    public function edit(int $id): string|RedirectResponse
    {
        if (($redirect = $this->guard('menus.view')) !== null) {
            return $redirect;
        }

        $repository = $this->menus();
        $menu = $repository->findMenu($id);

        if ($menu === null) {
            return redirect()->to('/admin/menus')->with('error', lang('Menus.Errors.menuNotFound'));
        }

        if ($this->request->getMethod() === 'POST') {
            if (($redirect = $this->guard('menus.manage')) !== null) {
                return $redirect;
            }

            try {
                $repository->updateMenu($id, $this->menuData());

                return redirect()->to('/admin/menus/' . $id . '/edit')
                    ->with('message', lang('Menus.Messages.updated'));
            } catch (\Throwable $e) {
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }
        }

        return $this->form($menu);
    }

    public function delete(int $id): RedirectResponse
    {
        if (($redirect = $this->guard('menus.manage')) !== null) {
            return $redirect;
        }

        try {
            $this->menus()->deleteMenu($id);

            return redirect()->to('/admin/menus')->with('message', lang('Menus.Messages.deleted'));
        } catch (\Throwable $e) {
            return redirect()->to('/admin/menus')->with('error', $e->getMessage());
        }
    }

    public function saveItem(int $menuId): RedirectResponse
    {
        if (($redirect = $this->guard('menus.manage')) !== null) {
            return $redirect;
        }

        $itemId = (int) $this->request->getPost('item_id');
        $itemId = $itemId > 0 ? $itemId : null;

        try {
            $item = $this->menus()->saveItem($menuId, $itemId, [
                'label' => (string) $this->request->getPost('label'),
                'target_type' => trim((string) $this->request->getPost('target_type')) ?: 'custom',
                'target' => (string) $this->request->getPost('target'),
                'parent_id' => $this->request->getPost('parent_id'),
                'enabled' => $this->request->getPost('enabled') !== null,
                'open_in_new' => $this->request->getPost('open_in_new') !== null,
            ]);

            return redirect()->to(site_url('admin/menus/' . $menuId . '/edit') . '?item=' . $item['id'] . '#menu-item-form')
                ->with('message', lang('Menus.Messages.itemSaved'));
        } catch (\Throwable $e) {
            return redirect()->to(site_url('admin/menus/' . $menuId . '/edit') . ($itemId === null ? '' : '?item=' . $itemId) . '#menu-item-form')
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function resources(): ResponseInterface
    {
        if (! $this->can('menus.manage')) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'items' => [],
            ]);
        }

        $providerId = trim((string) $this->request->getGet('provider'));
        $registry = service('mcmsNavigation');

        if ($providerId === '') {
            return $this->response->setJSON([
                'success' => true,
                'providers' => $registry->choices(),
            ]);
        }

        if (! $registry->has($providerId)) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'items' => [],
            ]);
        }

        $query = trim((string) $this->request->getGet('q'));

        return $this->response->setJSON([
            'success' => true,
            'items' => $registry->get($providerId)->search($query, 100),
        ]);
    }

    public function deleteItem(int $menuId, int $itemId): RedirectResponse
    {
        if (($redirect = $this->guard('menus.manage')) !== null) {
            return $redirect;
        }

        try {
            $this->menus()->deleteItem($menuId, $itemId);

            return redirect()->to('/admin/menus/' . $menuId . '/edit#menu-items')
                ->with('message', lang('Menus.Messages.itemDeleted'));
        } catch (\Throwable $e) {
            return redirect()->to('/admin/menus/' . $menuId . '/edit#menu-items')
                ->with('error', $e->getMessage());
        }
    }

    public function saveStructure(int $menuId): RedirectResponse
    {
        if (($redirect = $this->guard('menus.manage')) !== null) {
            return $redirect;
        }

        try {
            $raw = (string) $this->request->getPost('structure');
            $entries = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($entries)) {
                throw new JsonException(lang('Menus.Errors.invalidStructure'));
            }

            $this->menus()->updateStructure($menuId, array_values($entries));

            return redirect()->to('/admin/menus/' . $menuId . '/edit#menu-items')
                ->with('message', lang('Menus.Messages.structureSaved'));
        } catch (\Throwable $e) {
            return redirect()->to('/admin/menus/' . $menuId . '/edit#menu-items')
                ->with('error', $e->getMessage());
        }
    }

    private function form(?array $menu): string
    {
        $repository = $this->menus();
        $items = $menu === null ? [] : $repository->items($menu['id']);
        $tree = $menu === null ? [] : $repository->tree($menu['id']);

        $editItem = null;
        if ($menu !== null) {
            $itemId = (int) $this->request->getGet('item');
            if ($itemId > 0) {
                $editItem = $repository->findItem($menu['id'], $itemId);
            }
        }

        return view('MCMS\\Modules\\Menus\\Views\\form', [
            'title' => $menu === null ? lang('Menus.Admin.create') : lang('Menus.Admin.edit'),
            'adminMenu' => $this->adminMenu(),
            'user' => auth()->user(),
            'menu' => $menu,
            'items' => $items,
            'tree' => $tree,
            'editItem' => $editItem,
            'canManage' => $this->can('menus.manage'),
            'navigationProviders' => service('mcmsNavigation')->choices(),
        ]);
    }

    private function menuData(): array
    {
        return [
            'name' => (string) $this->request->getPost('name'),
            'handle' => (string) $this->request->getPost('handle'),
            'description' => (string) $this->request->getPost('description'),
        ];
    }

    private function menus(): MenuRepositoryInterface
    {
        $service = service('mcmsModuleServices')->get('menus.repository');

        if (! $service instanceof MenuRepositoryInterface) {
            throw new RuntimeException(lang('Menus.Errors.serviceUnavailable'));
        }

        return $service;
    }
}