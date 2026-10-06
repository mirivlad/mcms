<?php

declare(strict_types=1);

namespace MCMS\Modules\Orders\Controllers;

use App\Controllers\Admin\BaseAdminController;
use CodeIgniter\HTTP\RedirectResponse;
use MCMS\Modules\Orders\Api\OrdersInterface;
use Throwable;

final class Admin extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if (($redirect = $this->guard('orders.view')) !== null) {
            return $redirect;
        }

        $query = mb_substr(trim((string) $this->request->getGet('q')), 0, 200);
        $status = mb_substr(trim((string) $this->request->getGet('status')), 0, 24);
        if ($status !== '' && ! in_array($status, $this->orders()->statuses(), true)) {
            $status = '';
        }

        $count = $this->orders()->count($query, $status);
        $lastPage = max(1, (int) ceil($count / 50));
        $page = min($lastPage, max(1, (int) $this->request->getGet('page')));

        return view('MCMS\\Modules\\Orders\\Views\\index', [
            'title' => lang('Orders.title'),
            'adminMenu' => $this->adminMenu(),
            'user' => auth()->user(),
            'orders' => $this->orders()->list($query, $status, 50, ($page - 1) * 50),
            'query' => $query,
            'status' => $status,
            'statuses' => $this->orders()->statuses(),
            'count' => $count,
            'page' => $page,
            'lastPage' => $lastPage,
        ]);
    }

    public function show(int $id): string|RedirectResponse
    {
        if (($redirect = $this->guard('orders.view')) !== null) {
            return $redirect;
        }
        $order = $this->orders()->find($id);
        if ($order === null) {
            return redirect()->to('/admin/orders')->with('error', lang('Orders.notFound'));
        }

        return view('MCMS\\Modules\\Orders\\Views\\show', [
            'title' => lang('Orders.orderNumber', [$id]),
            'adminMenu' => $this->adminMenu(),
            'user' => auth()->user(),
            'order' => $order,
            'canManage' => $this->can('orders.manage'),
        ]);
    }

    public function status(int $id): RedirectResponse
    {
        if (($redirect = $this->guard('orders.manage')) !== null) {
            return $redirect;
        }

        try {
            $this->orders()->transition(
                $id,
                (string) $this->request->getPost('status'),
                (int) auth()->id(),
                (string) $this->request->getPost('note'),
            );

            return redirect()->to('/admin/orders/' . $id)->with('message', lang('Orders.statusSaved'));
        } catch (Throwable $e) {
            return redirect()->to('/admin/orders/' . $id)->with('error', $e->getMessage());
        }
    }

    private function orders(): OrdersInterface
    {
        return service('mcmsModuleServices')->get('orders.orders');
    }
}
