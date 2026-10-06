<?php

declare(strict_types=1);

namespace MCMS\Modules\Orders\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use MCMS\Modules\Orders\Api\OrdersInterface;
use Throwable;

final class Checkout extends BaseController
{
    public function create(): RedirectResponse
    {
        try {
            $order = $this->orders()->createFromCart([
                'name' => (string) $this->request->getPost('name'),
                'phone' => (string) $this->request->getPost('phone'),
                'email' => (string) $this->request->getPost('email'),
                'comment' => (string) $this->request->getPost('comment'),
            ]);

            return redirect()->to('/cart')
                ->with('message', lang('Orders.created', [(int) $order['id']]));
        } catch (Throwable $e) {
            return redirect()->to('/cart')
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    private function orders(): OrdersInterface
    {
        return service('mcmsModuleServices')->get('orders.orders');
    }
}
