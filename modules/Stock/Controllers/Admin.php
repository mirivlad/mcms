<?php

declare(strict_types=1);

namespace MCMS\Modules\Stock\Controllers;

use App\Controllers\Admin\BaseAdminController;
use CodeIgniter\HTTP\RedirectResponse;
use MCMS\Modules\Stock\Api\StockInterface;

final class Admin extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if (($redirect = $this->guard('stock.view')) !== null) {
            return $redirect;
        }

        $query = mb_substr(trim((string) $this->request->getGet('q')), 0, 200);
        $count = $this->stock()->count($query);
        $lastPage = max(1, (int) ceil($count / 50));
        $page = min($lastPage, max(1, (int) $this->request->getGet('page')));

        return view('MCMS\\Modules\\Stock\\Views\\index', [
            'title' => lang('Stock.title'),
            'adminMenu' => $this->adminMenu(),
            'user' => auth()->user(),
            'rows' => $this->stock()->list($query, 50, ($page - 1) * 50),
            'query' => $query,
            'count' => $count,
            'page' => $page,
            'lastPage' => $lastPage,
            'canManage' => $this->can('stock.manage'),
            'returnPath' => $this->returnPath(),
        ]);
    }

    public function update(int $variantId): RedirectResponse
    {
        if (($redirect = $this->guard('stock.manage')) !== null) {
            return $redirect;
        }

        return $this->performUpdate($variantId, '/admin/stock');
    }

    private function performUpdate(int $variantId, string $fallback): RedirectResponse
    {
        try {
            $quantity = filter_var($this->request->getPost('quantity'), FILTER_VALIDATE_INT);
            if ($quantity === false) {
                throw new \InvalidArgumentException(lang('Stock.invalidQuantity'));
            }
            $this->stock()->setOnHand(
                $variantId,
                $quantity,
                (int) auth()->id(),
                (string) $this->request->getPost('note')
            );

            return redirect()->to($this->safeReturn((string) $this->request->getPost('return'), $fallback))
                ->with('message', lang('Stock.saved'));
        } catch (\Throwable $e) {
            return redirect()->to($this->safeReturn((string) $this->request->getPost('return'), $fallback))
                ->with('error', $e->getMessage());
        }
    }

    private function returnPath(): string
    {
        $uri = $this->request->getUri();
        $path = '/' . ltrim($uri->getPath(), '/');
        return $uri->getQuery() === '' ? $path : $path . '?' . $uri->getQuery();
    }

    private function safeReturn(string $return, string $fallback): string
    {
        $return = trim($return);
        if ($return === '' || ! str_starts_with($return, '/') || str_starts_with($return, '//') || str_contains($return, "
") || str_contains($return, "")) {
            return $fallback;
        }

        return $return;
    }

    private function stock(): StockInterface
    {
        return service('mcmsModuleServices')->get('stock.inventory');
    }
}
