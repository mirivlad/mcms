<?php

declare(strict_types=1);

namespace MCMS\Modules\Delivery\Controllers;

use App\Controllers\Admin\BaseAdminController;
use CodeIgniter\HTTP\RedirectResponse;
use MCMS\Modules\Delivery\Api\DeliveryInterface;
use Throwable;

final class Admin extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if (($redirect = $this->guard('delivery.view')) !== null) {
            return $redirect;
        }

        return view('MCMS\\Modules\\Delivery\\Views\\index', [
            'title' => lang('Delivery.title'),
            'adminMenu' => $this->adminMenu(),
            'user' => auth()->user(),
            'methods' => $this->delivery()->all(),
            'canManage' => $this->can('delivery.manage'),
        ]);
    }

    public function form(?int $id = null): string|RedirectResponse
    {
        if (($redirect = $this->guard('delivery.manage')) !== null) {
            return $redirect;
        }

        $method = $id === null ? null : $this->delivery()->find($id);
        if ($id !== null && $method === null) {
            return redirect()->to($this->settingsPath('/admin/delivery'))->with('error', lang('Delivery.notFound'));
        }

        return view('MCMS\\Modules\\Delivery\\Views\\form', [
            'title' => $id === null ? lang('Delivery.create') : lang('Delivery.edit'),
            'adminMenu' => $this->adminMenu(),
            'user' => auth()->user(),
            'method' => $method,
        ]);
    }

    public function save(?int $id = null): RedirectResponse
    {
        if (($redirect = $this->guard('delivery.manage')) !== null) {
            return $redirect;
        }

        try {
            $method = $this->delivery()->save($id, [
                'name' => $this->request->getPost('name'),
                'type' => $this->request->getPost('type'),
                'price' => $this->request->getPost('price'),
                'enabled' => $this->request->getPost('enabled'),
                'position' => $this->request->getPost('position'),
            ]);

            return redirect()->to($this->settingsPath('/admin/delivery/' . $method['id']))->with('message', lang('Delivery.saved'));
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function delete(int $id): RedirectResponse
    {
        if (($redirect = $this->guard('delivery.manage')) !== null) {
            return $redirect;
        }

        try {
            $this->delivery()->delete($id);

            return redirect()->to($this->settingsPath('/admin/delivery'))->with('message', lang('Delivery.deleted'));
        } catch (Throwable $e) {
            return redirect()->to($this->settingsPath('/admin/delivery'))->with('error', $e->getMessage());
        }
    }

    public function order(): RedirectResponse
    {
        if (($redirect = $this->guard('delivery.manage')) !== null) {
            return $redirect;
        }

        try {
            $this->delivery()->reorder((array) $this->request->getPost('position'));

            return redirect()->to($this->settingsPath('/admin/delivery'))->with('message', lang('Delivery.orderSaved'));
        } catch (Throwable $e) {
            return redirect()->to($this->settingsPath('/admin/delivery'))->with('error', $e->getMessage());
        }
    }

    private function delivery(): DeliveryInterface
    {
        return service('mcmsModuleServices')->get('delivery.methods');
    }
}
