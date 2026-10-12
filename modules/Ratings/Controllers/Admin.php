<?php

declare(strict_types=1);

namespace MCMS\Modules\Ratings\Controllers;

use App\Controllers\Admin\BaseAdminController;
use CodeIgniter\HTTP\RedirectResponse;
use MCMS\Modules\Ratings\Services\Ratings;

final class Admin extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if (($redirect = $this->guard('ratings.view')) !== null) {
            return $redirect;
        }
        $repository = $this->repository();
        $total = $repository->count();
        $lastPage = max(1, (int) ceil($total / 50));
        $page = max(1, min($lastPage, (int) $this->request->getGet('page')));
        $votes = $repository->list(50, ($page - 1) * 50);
        $targets = [];
        foreach ($votes as $vote) {
            $targets[$vote['id']] = service('mcmsNavigation')->resolve($vote['target_type'], $vote['target_id']);
        }
        return view('MCMS\Modules\Ratings\Views\index', [
            'adminMenu' => $this->adminMenu(), 'user' => auth()->user(),
            'votes' => $votes, 'targets' => $targets, 'page' => $page,
            'lastPage' => $lastPage, 'canManage' => $this->can('ratings.manage'),
        ]);
    }

    public function settings(): string|RedirectResponse
    {
        if (($redirect = $this->guard('ratings.manage')) !== null) {
            return $redirect;
        }
        $choices = [];
        foreach (service('mcmsNavigation')->choices() as $entry) {
            $choices[$entry['id']] = $entry['label'];
        }
        $enabled = array_filter(array_map('trim', explode(',', (string) setting('ratings.enabled_resources'))));
        foreach ($enabled as $id) {
            $choices[$id] ??= $id;
        }
        ksort($choices);
        if ($this->request->getMethod() === 'POST') {
            $raw = $this->request->getPost('resources');
            $selected = is_array($raw) ? array_filter($raw, 'is_string') : [];
            $selected = array_values(array_intersect(array_keys($choices), $selected));
            service('settings')->set('ratings.allow_guests', $this->request->getPost('allow_guests') === '1');
            service('settings')->set('ratings.enabled_resources', implode(',', $selected));
            return redirect()->to('/admin/ratings/settings')->with('message', lang('Ratings.Admin.saved'));
        }
        return view('MCMS\Modules\Ratings\Views\settings', [
            'adminMenu' => $this->adminMenu(), 'user' => auth()->user(),
            'choices' => $choices, 'enabled' => $enabled,
            'allowGuests' => (bool) setting('ratings.allow_guests'),
        ]);
    }

    public function delete(int $id): RedirectResponse
    {
        if (($redirect = $this->guard('ratings.manage')) !== null) {
            return $redirect;
        }
        $this->repository()->delete($id);
        return redirect()->to('/admin/ratings');
    }

    private function repository(): Ratings
    {
        return service('mcmsModuleServices')->get('ratings.repository');
    }
}
