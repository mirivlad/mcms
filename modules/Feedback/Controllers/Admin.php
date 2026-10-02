<?php

declare(strict_types=1);

namespace MCMS\Modules\Feedback\Controllers;

use App\Controllers\Admin\BaseAdminController;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use MCMS\Modules\Feedback\Api\FeedbackRepositoryInterface;

final class Admin extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if (($redirect = $this->guard('feedback.view')) !== null) {
            return $redirect;
        }
        $count = $this->repository()->count();
        $lastPage = max(1, (int) ceil($count / 30));
        $page = max(1, min($lastPage, (int) $this->request->getGet('page')));
        return view('MCMS\\Modules\\Feedback\\Views\\index', [
            'adminMenu' => $this->adminMenu(), 'user' => auth()->user(), 'messages' => $this->repository()->list(30, ($page - 1) * 30),
            'page' => $page, 'lastPage' => $lastPage,
        ]);
    }

    public function show(int $id): string|RedirectResponse
    {
        if (($redirect = $this->guard('feedback.view')) !== null) {
            return $redirect;
        }
        $message = $this->repository()->find($id);
        if ($message === null) { throw PageNotFoundException::forPageNotFound(); }
        return view('MCMS\\Modules\\Feedback\\Views\\show', [
            'adminMenu' => $this->adminMenu(), 'user' => auth()->user(), 'message' => $message,
            'canManage' => $this->can('feedback.manage'), 'canDelete' => $this->can('feedback.delete'),
        ]);
    }

    public function markRead(int $id): RedirectResponse
    {
        if (($redirect = $this->guard('feedback.manage')) !== null) {
            return $redirect;
        }
        $this->repository()->markRead($id);
        return redirect()->to('/admin/feedback/' . $id);
    }

    public function delete(int $id): RedirectResponse
    {
        if (($redirect = $this->guard('feedback.delete')) !== null) {
            return $redirect;
        }
        $this->repository()->delete($id);
        return redirect()->to('/admin/feedback')->with('message', lang('Feedback.Messages.deleted'));
    }

    private function repository(): FeedbackRepositoryInterface
    {
        return service('mcmsModuleServices')->get('feedback.repository');
    }
}
