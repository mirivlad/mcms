<?php

declare(strict_types=1);

namespace MCMS\Modules\Comments\Controllers;

use App\Controllers\Admin\BaseAdminController;
use CodeIgniter\HTTP\RedirectResponse;
use InvalidArgumentException;
use MCMS\Modules\Comments\Services\Comments;

final class Admin extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if (($redirect = $this->guard('comments.view')) !== null) {
            return $redirect;
        }
        $status = (string) ($this->request->getGet('status') ?: 'pending');
        if (! in_array($status, ['pending', 'published', 'rejected', 'spam', 'all'], true)) {
            $status = 'pending';
        }
        $repository = $this->repository();
        $total = $repository->count($status);
        $lastPage = max(1, (int) ceil($total / 30));
        $page = max(1, min($lastPage, (int) $this->request->getGet('page')));
        $comments = $repository->list($status, 30, ($page - 1) * 30);
        $reports = [];
        $targets = [];
        foreach ($comments as $comment) {
            $reports[$comment['id']] = $repository->reports((int) $comment['id']);
            $targets[$comment['id']] = service('mcmsNavigation')->resolve(
                $comment['target_type'], $comment['target_id']
            );
        }
        return view('MCMS\Modules\Comments\Views\index', [
            'adminMenu' => $this->adminMenu(), 'user' => auth()->user(),
            'comments' => $comments, 'reports' => $reports, 'targets' => $targets,
            'status' => $status, 'page' => $page, 'lastPage' => $lastPage,
            'canModerate' => $this->can('comments.moderate'),
            'canDelete' => $this->can('comments.delete'),
        ]);
    }

    public function settings(): string|RedirectResponse
    {
        if (($redirect = $this->guard('comments.moderate')) !== null) {
            return $redirect;
        }
        $available = service('mcmsNavigation')->choices();
        $choices = [];
        foreach ($available as $entry) {
            $choices[$entry['id']] = $entry['label'];
        }
        $enabled = array_filter(array_map('trim', explode(',', (string) setting('comments.enabled_resources'))));
        foreach ($enabled as $id) {
            $choices[$id] ??= $id;
        }
        ksort($choices);

        if ($this->request->getMethod() === 'POST') {
            $requested = $this->request->getPost('resources');
            $requested = is_array($requested) ? $requested : [];
            $selected = array_values(array_intersect(array_keys($choices), array_filter($requested, 'is_string')));
            $depth = (int) $this->request->getPost('max_depth');
            if ($depth < 1 || $depth > 8) {
                return redirect()->back()->with('error', lang('Comments.Errors.invalid'));
            }
            $settings = service('settings');
            $settings->set('comments.allow_guests', $this->request->getPost('allow_guests') === '1');
            $settings->set('comments.auto_approve_users', $this->request->getPost('auto_approve_users') === '1');
            $settings->set('comments.max_depth', $depth);
            $settings->set('comments.enabled_resources', implode(',', $selected));
            return redirect()->to('/admin/comments/settings')->with('message', lang('Comments.Admin.saved'));
        }
        return view('MCMS\Modules\Comments\Views\settings', [
            'adminMenu' => $this->adminMenu(), 'user' => auth()->user(),
            'choices' => $choices, 'enabled' => $enabled,
            'allowGuests' => (bool) setting('comments.allow_guests'),
            'autoApprove' => (bool) setting('comments.auto_approve_users'),
            'maxDepth' => (int) setting('comments.max_depth'),
        ]);
    }

    public function status(int $id): RedirectResponse
    {
        if (($redirect = $this->guard('comments.moderate')) !== null) {
            return $redirect;
        }
        $status = (string) $this->request->getPost('status');
        try {
            $this->repository()->setStatus($id, $status);
        } catch (InvalidArgumentException $e) {
            return redirect()->to('/admin/comments')->with('error', $e->getMessage());
        }
        return redirect()->to('/admin/comments');
    }

    public function delete(int $id): RedirectResponse
    {
        if (($redirect = $this->guard('comments.delete')) !== null) {
            return $redirect;
        }
        $this->repository()->delete($id);
        return redirect()->to('/admin/comments');
    }

    private function repository(): Comments
    {
        return service('mcmsModuleServices')->get('comments.repository');
    }
}
