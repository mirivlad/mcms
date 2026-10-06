<?php

declare(strict_types=1);

namespace MCMS\Modules\Stock\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use MCMS\Modules\Stock\Api\StockInterface;
use Throwable;

final class Quick extends BaseController
{
    public function update(int $variantId): RedirectResponse|ResponseInterface
    {
        $return = $this->safeReturn((string) $this->request->getPost('return'));
        $user = auth()->user();
        if ($user === null || ! $user->can('stock.manage')) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(403)->setJSON([
                    'success' => false,
                    'message' => lang('MCMS.errors.permissionDenied'),
                    'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()],
                ]);
            }

            return redirect()->to('/')->with('error', lang('MCMS.errors.permissionDenied'));
        }

        try {
            $quantity = filter_var($this->request->getPost('quantity'), FILTER_VALIDATE_INT);
            if ($quantity === false) {
                throw new \InvalidArgumentException(lang('Stock.invalidQuantity'));
            }
            $level = $this->stock()->setOnHand(
                $variantId,
                $quantity,
                (int) auth()->id(),
                (string) $this->request->getPost('note')
            );

            if ($this->request->isAJAX()) {
                return $this->response->setJSON([
                    'success' => true,
                    'message' => lang('Stock.saved'),
                    'variant_id' => $variantId,
                    'stock' => $level,
                    'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()],
                ]);
            }

            return redirect()->to($return)->with('message', lang('Stock.saved'));
        } catch (Throwable $e) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()],
                ]);
            }

            return redirect()->to($return)->with('error', $e->getMessage());
        }
    }

    private function safeReturn(string $return): string
    {
        $return = trim($return);
        if ($return === '' || ! str_starts_with($return, '/') || str_starts_with($return, '//') || str_contains($return, "\n") || str_contains($return, "\r")) {
            return '/shop';
        }

        return $return;
    }

    private function stock(): StockInterface
    {
        return service('mcmsModuleServices')->get('stock.inventory');
    }
}
