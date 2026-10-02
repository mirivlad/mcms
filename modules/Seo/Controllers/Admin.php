<?php

declare(strict_types=1);

namespace MCMS\Modules\Seo\Controllers;

use App\Controllers\Admin\BaseAdminController;
use CodeIgniter\HTTP\RedirectResponse;
use MCMS\Modules\Seo\Services\RedirectRepository;

final class Admin extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if (($redirect = $this->guard('seo.manage')) !== null) {
            return $redirect;
        }
        $repository = $this->repository();
        $lastPage = max(1, (int) ceil($repository->count() / 50));
        $page = max(1, min($lastPage, (int) $this->request->getGet('page')));
        return view('MCMS\\Modules\\Seo\\Views\\index', ['adminMenu' => $this->adminMenu(), 'user' => auth()->user(),
            'redirects' => $repository->list(50, ($page - 1) * 50), 'page' => $page, 'lastPage' => $lastPage]);
    }

    public function delete(): RedirectResponse
    {
        if (($redirect = $this->guard('seo.manage')) !== null) {
            return $redirect;
        }
        $this->repository()->delete((string) $this->request->getPost('source'));
        return redirect()->to('/admin/seo')->with('message', lang('Seo.Messages.deleted'));
    }

    private function repository(): RedirectRepository
    {
        return service('mcmsModuleServices')->get('seo.redirects');
    }
}
