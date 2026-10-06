<?php

declare(strict_types=1);

namespace MCMS\Modules\Stock\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use MCMS\Modules\Stock\Api\StockInterface;

final class Quick extends BaseController
{
    public function update(int $variantId): RedirectResponse
    {
        $return = $this->safeReturn((string) $this->request->getPost('return'));
        $user = auth()->user();
        if ($user === null || ! $user->can('stock.manage')) {
            return redirect()->to('/')->with('error', lang('MCMS.errors.permissionDenied'));
        }

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

            return redirect()->to($return)->with('message', lang('Stock.saved'));
        } catch (\Throwable $e) {
            return redirect()->to($return)->with('error', $e->getMessage());
        }
    }

    private function safeReturn(string $return): string
    {
        $return = trim($return);
        if ($return === '' || ! str_starts_with($return, '/') || str_starts_with($return, '//') || str_contains($return, "
") || str_contains($return, "")) {
            return '/shop';
        }

        return $return;
    }

    private function stock(): StockInterface
    {
        return service('mcmsModuleServices')->get('stock.inventory');
    }
}
