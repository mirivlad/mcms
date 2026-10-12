<?php

declare(strict_types=1);

namespace MCMS\Modules\Catalogue\Controllers;

use App\Controllers\Admin\BaseAdminController;
use CodeIgniter\HTTP\RedirectResponse;
use MCMS\Modules\Catalogue\Api\CatalogueInterface;
use MCMS\Modules\ProductImages\Api\ProductImagesInterface;

final class Admin extends BaseAdminController
{
    public function store(): string|RedirectResponse
    {
        if (($redirect = $this->guard('catalogue.view')) !== null) {
            return $redirect;
        }
        $stock = $this->stock();

        return view('MCMS\\Modules\\Catalogue\\Views\\store', [
            'title' => lang('Catalogue.manageStore'),
            'adminMenu' => $this->adminMenu(),
            'user' => auth()->user(),
            'showStock' => $stock !== null && ($this->can('stock.view') || $this->can('stock.manage')),
        ]);
    }

    public function settings(): string|RedirectResponse
    {
        if ($denied = $this->guard('catalogue.settings')) {
            return $denied;
        }
        $settings = service('settings');
        if ($this->request->getMethod() === 'POST') {
            $perPage = filter_var($this->request->getPost('per_page'), FILTER_VALIDATE_INT);
            if ($perPage === false || $perPage < 1 || $perPage > 100) {
                return redirect()->to($this->settingsPath('/admin/catalogue/settings'))->withInput()->with('error', lang('CatalogueSettings.invalid'));
            }
            $style = (string) ($this->request->getPost('category_menu_style') ?? ($settings->get('catalogue.category_menu_style') ?? 'tree'));
            $scope = (string) ($this->request->getPost('secondary_menu_scope') ?? ($settings->get('catalogue.secondary_menu_scope') ?? 'listing'));
            $menuIdInput = $this->request->getPost('secondary_menu_id');
            $menuId = $menuIdInput === null ? (int) ($settings->get('catalogue.secondary_menu_id') ?? 0)
                : filter_var($menuIdInput, FILTER_VALIDATE_INT);
            $menus = service('mcmsModuleServices');
            $menuSource = $menus->has('menus.repository') ? $menus->get('menus.repository') : null;
            if (! in_array($style, ['tree', 'collapsed', 'horizontal'], true)
                || ! in_array($scope, ['listing', 'all_shop'], true)
                || $menuId === false || $menuId < 0
                || ($menuId > 0 && $menuSource !== null && $menuSource->findMenu($menuId) === null)
                || ($menuId > 0 && $menuSource === null && $menuId !== (int) ($settings->get('catalogue.secondary_menu_id') ?? 0))) {
                return redirect()->to($this->settingsPath('/admin/catalogue/settings'))->withInput()->with('error', lang('CatalogueSettings.invalid'));
            }
            $settings->set('catalogue.per_page', $perPage);
            $settings->set('catalogue.category_menu_style', $style);
            $settings->set('catalogue.secondary_menu_id', $menuId);
            $settings->set('catalogue.secondary_menu_scope', $scope);
            return redirect()->to($this->settingsPath('/admin/catalogue/settings'))->with('message', lang('CatalogueSettings.saved'));
        }
        $services = service('mcmsModuleServices');
        $menuSource = $services->has('menus.repository') ? $services->get('menus.repository') : null;
        return view('MCMS\\Modules\\Catalogue\\Views\\settings', [
            'adminMenu' => $this->adminMenu(), 'title' => lang('CatalogueSettings.title'),
            'perPage' => (int) ($settings->get('catalogue.per_page') ?? 24),
            'categoryMenuStyle' => (string) ($settings->get('catalogue.category_menu_style') ?? 'tree'),
            'secondaryMenuId' => (int) ($settings->get('catalogue.secondary_menu_id') ?? 0),
            'secondaryMenuScope' => (string) ($settings->get('catalogue.secondary_menu_scope') ?? 'listing'),
            'menuAvailable' => $menuSource !== null,
            'availableMenus' => $menuSource?->list() ?? [],
        ]);
    }

    public function index(): string|RedirectResponse
    {
        if (($redirect = $this->guard('catalogue.view')) !== null) {
            return $redirect;
        }

        $query = mb_substr(trim((string) $this->request->getGet('q')), 0, 200);
        $count = $this->catalogue()->count($query);
        $lastPage = max(1, (int) ceil($count / 24));
        $page = min($lastPage, max(1, (int) $this->request->getGet('page')));
        $products = $this->catalogue()->list($query, null, 24, ($page - 1) * 24);
        $stock = $this->stock();
        $canManageStock = $stock !== null && $this->can('stock.manage');

        return view('MCMS\\Modules\\Catalogue\\Views\\index', [
            'title' => lang('Catalogue.manageProducts'),
            'adminMenu' => $this->adminMenu(),
            'user' => auth()->user(),
            'products' => $products,
            'query' => $query,
            'count' => $count,
            'page' => $page,
            'lastPage' => $lastPage,
            'canCreate' => $this->can('catalogue.create'),
            'canEdit' => $this->can('catalogue.edit'),
            'canDelete' => $this->can('catalogue.delete'),
            'canManageStock' => $canManageStock,
            'stockLevels' => $canManageStock ? $stock->levels($this->variantIds($products)) : [],
            'stockReturn' => $this->returnPath(),
        ]);
    }

    public function create(): string|RedirectResponse
    {
        if (($redirect = $this->guard('catalogue.create')) !== null) {
            return $redirect;
        }

        return $this->editor(null);
    }

    public function edit(int $id): string|RedirectResponse
    {
        if (($redirect = $this->guard('catalogue.edit')) !== null) {
            return $redirect;
        }
        $product = $this->catalogue()->find($id);

        return $product === null
            ? redirect()->to('/admin/catalogue/products')->with('error', lang('Catalogue.notFound'))
            : $this->editor($product);
    }

    public function delete(int $id): RedirectResponse
    {
        if (($redirect = $this->guard('catalogue.delete')) !== null) {
            return $redirect;
        }
        try {
            if ($this->request->getPost('confirm') !== '1') {
                throw new \RuntimeException(lang('Catalogue.confirmDelete'));
            }
            $this->catalogue()->delete($id);

            return redirect()->to('/admin/catalogue/products')->with('message', lang('Catalogue.deleted'));
        } catch (\Throwable $e) {
            return redirect()->to('/admin/catalogue/products')->with('error', $e->getMessage());
        }
    }

    private function editor(?array $product): string|RedirectResponse
    {
        $images = $this->images();
        if ($this->request->getMethod() === 'POST') {
            try {
                $data = (array) $this->request->getPost();
                if ($product !== null) {
                    $data['image_set_id'] = $product['image_set_id'];
                }
                $saved = $this->catalogue()->save($product['id'] ?? null, $data, (int) auth()->id());

                return redirect()->to('/admin/catalogue/' . $saved['id'] . '/edit')->with('message', lang('Catalogue.saved'));
            } catch (\Throwable $e) {
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }
        }

        $set = $product['image_set_id'] ?? (string) old('image_set_id', '');
        if ($set !== '') {
            try {
                $images->authorize($set, (int) auth()->id());
            } catch (\Throwable) {
                $set = '';
            }
        }
        $set = $set ?: $images->createSet((int) auth()->id());
        $stock = $this->stock();
        $canManageStock = $product !== null && $stock !== null && $this->can('stock.manage');

        return view('MCMS\\Modules\\Catalogue\\Views\\form', [
            'title' => lang($product === null ? 'Catalogue.create' : 'Catalogue.edit'),
            'adminMenu' => $this->adminMenu(),
            'user' => auth()->user(),
            'product' => $product,
            'setId' => $set,
            'items' => $images->images($set),
            'canImages' => $this->can('product-images.manage'),
            'categories' => $this->catalogue()->categories(),
            'canManageStock' => $canManageStock,
            'stockLevels' => $canManageStock ? $stock->levels(array_column($product['variants'], 'id')) : [],
            'stockReturn' => $this->returnPath(),
        ]);
    }

    private function stock(): ?object
    {
        $services = service('mcmsModuleServices');

        return $services->has('stock.inventory') ? $services->get('stock.inventory') : null;
    }

    private function variantIds(array $products): array
    {
        $ids = [];
        foreach ($products as $product) {
            foreach ($product['variants'] ?? [] as $variant) {
                $ids[] = (int) $variant['id'];
            }
        }

        return $ids;
    }

    private function returnPath(): string
    {
        $uri = $this->request->getUri();
        $path = '/' . ltrim($uri->getPath(), '/');

        return $uri->getQuery() === '' ? $path : $path . '?' . $uri->getQuery();
    }

    private function catalogue(): CatalogueInterface
    {
        return service('mcmsModuleServices')->get('catalogue.repository');
    }

    private function images(): ProductImagesInterface
    {
        return service('mcmsModuleServices')->get('product-images.library');
    }
}
