<?php

declare(strict_types=1);

namespace MCMS\Modules\Catalogue\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use MCMS\Modules\Catalogue\Api\CatalogueInterface;
use MCMS\Modules\Catalogue\Services\Categories;

final class Shop extends BaseController
{
    public function index(): string
    {
        return $this->listing();
    }

    public function category(string $slug): string
    {
        $category = $this->categories()->bySlug($slug) ?? throw PageNotFoundException::forPageNotFound();

        return $this->listing($category);
    }

    private function listing(?array $selectedCategory = null): string
    {
        $query = mb_substr(trim((string) $this->request->getGet('q')), 0, 200);
        $category = $selectedCategory['slug'] ?? mb_substr(trim((string) $this->request->getGet('category')), 0, 190);
        $count = $this->catalogue()->count($query, 'published', $category);
        $lastPage = max(1, (int) ceil($count / 24));
        $page = min($lastPage, max(1, (int) $this->request->getGet('page')));
        $products = $this->catalogue()->list($query, 'published', 24, ($page - 1) * 24, $category);
        $stock = $this->stock();
        $cart = $this->cart();
        $canManageStock = $stock !== null && $this->canManageStock();
        $cartEnabled = $cart !== null && $stock !== null;
        $variantIds = $this->variantIds($products);
        $levels = ($canManageStock || $cartEnabled) && $stock !== null ? $stock->levels($variantIds) : [];

        return service('mcmsThemes')->render('catalogue.index', [
            'products' => $products,
            'query' => $query,
            'category' => $category,
            'categories' => $this->catalogue()->categories(true),
            'selectedCategory' => $selectedCategory,
            'count' => $count,
            'page' => $page,
            'lastPage' => $lastPage,
            'canManageStock' => $canManageStock,
            'stockLevels' => $canManageStock ? $levels : [],
            'stockReturn' => $this->returnPath(),
            'cartEnabled' => $cartEnabled,
            'cartCount' => $cartEnabled ? $cart->count() : 0,
            'cartStockLevels' => $cartEnabled ? $levels : [],
            'cartReturn' => $this->returnPath(),
            'metaTitle' => ($selectedCategory['name'] ?? lang('Catalogue.name')) . ' · ' . setting('Core.siteName'),
        ], 'MCMS\\Modules\\Catalogue\\Views\\public_index');
    }

    public function show(string $slug): string
    {
        $product = $this->catalogue()->findPublishedBySlug($slug) ?? throw PageNotFoundException::forPageNotFound();
        $stock = $this->stock();
        $cart = $this->cart();
        $canManageStock = $stock !== null && $this->canManageStock();
        $cartEnabled = $cart !== null && $stock !== null;
        $variantIds = array_column($product['variants'], 'id');
        $levels = ($canManageStock || $cartEnabled) && $stock !== null ? $stock->levels($variantIds) : [];

        return service('mcmsThemes')->render('catalogue.product', [
            'product' => $product,
            'metaTitle' => $product['title'] . ' · ' . setting('Core.siteName'),
            'metaDescription' => $product['summary'],
            'canManageStock' => $canManageStock,
            'stockLevels' => $canManageStock ? $levels : [],
            'stockReturn' => $this->returnPath(),
            'cartEnabled' => $cartEnabled,
            'cartCount' => $cartEnabled ? $cart->count() : 0,
            'cartStockLevels' => $cartEnabled ? $levels : [],
            'cartReturn' => $this->returnPath(),
        ], 'MCMS\\Modules\\Catalogue\\Views\\public_product');
    }

    public function image(string $slug, int $id, string $size): ResponseInterface
    {
        $product = $this->catalogue()->findPublishedBySlug($slug);
        if ($product === null || ! in_array($size, ['thumb', 'display', 'full'], true)) {
            throw PageNotFoundException::forPageNotFound();
        }
        $file = service('mcmsModuleServices')->get('product-images.library')->file($product['image_set_id'], $id, $size);
        if ($file === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response->setHeader('Content-Type', $file['mime'])
            ->setHeader('Cache-Control', 'no-cache')->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody(file_get_contents($file['path']));
    }

    public function asset(string $name): ResponseInterface
    {
        $types = [
            'shop.css' => 'text/css; charset=UTF-8',
            'shop.js' => 'text/javascript; charset=UTF-8',
            'categories.css' => 'text/css; charset=UTF-8',
            'categories.js' => 'text/javascript; charset=UTF-8',
            'tabler-icons.json' => 'application/json',
        ];
        if (! isset($types[$name])) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response->setHeader('Content-Type', $types[$name])
            ->setBody(file_get_contents(dirname(__DIR__) . '/assets/' . $name));
    }

    public function categoryIcon(int $id): ResponseInterface
    {
        $path = $this->categories()->imageFile($id) ?? throw PageNotFoundException::forPageNotFound();

        return $this->response->setHeader('Content-Type', 'image/webp')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'no-cache')
            ->setBody(file_get_contents($path));
    }

    private function cart(): ?object
    {
        $services = service('mcmsModuleServices');

        return $services->has('cart.cart') ? $services->get('cart.cart') : null;
    }

    private function stock(): ?object
    {
        $services = service('mcmsModuleServices');

        return $services->has('stock.inventory') ? $services->get('stock.inventory') : null;
    }

    private function canManageStock(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can('stock.manage');
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

    private function categories(): Categories
    {
        return service('mcmsModuleServices')->get('catalogue.categories');
    }

    private function catalogue(): CatalogueInterface
    {
        return service('mcmsModuleServices')->get('catalogue.repository');
    }
}
