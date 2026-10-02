<?php

declare(strict_types=1);

namespace MCMS\Modules\Pages\Controllers;

use App\Controllers\Admin\BaseAdminController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use MCMS\Modules\Media\Api\MediaLibraryInterface;
use MCMS\Modules\Pages\Api\PageRepositoryInterface;
use RuntimeException;

final class Admin extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if (($redirect = $this->guard('pages.view')) !== null) {
            return $redirect;
        }

        $query = trim((string) $this->request->getGet('q'));
        $status = trim((string) $this->request->getGet('status'));
        $status = in_array($status, ['draft', 'published'], true) ? $status : null;
        $pages = $this->pages();
        $perPage = 50;
        $count = $pages->count($query, $status);
        $lastPage = max(1, (int) ceil($count / $perPage));
        $page = max(1, min((int) $this->request->getGet('page'), $lastPage));

        return view('MCMS\\Modules\\Pages\\Views\\index', [
            'title' => lang('Pages.Admin.title'),
            'adminMenu' => $this->adminMenu(),
            'user' => auth()->user(),
            'pages' => $pages->list($query, $status, $perPage, ($page - 1) * $perPage),
            'count' => $count,
            'page' => $page,
            'lastPage' => $lastPage,
            'query' => $query,
            'status' => $status,
            'canCreate' => $this->can('pages.create'),
            'canEdit' => $this->can('pages.edit'),
            'canDelete' => $this->can('pages.delete'),
            'canSettings' => $this->can('pages.settings'),
        ]);
    }

    public function create(): string|RedirectResponse
    {
        if (($redirect = $this->guard('pages.create')) !== null) {
            return $redirect;
        }

        if ($this->request->getMethod() === 'POST') {
            try {
                $page = $this->pages()->create(
                    $this->formData(),
                    auth()->id() === null ? null : (int) auth()->id(),
                );

                return redirect()->to('/admin/pages/' . $page['id'] . '/edit')
                    ->with('message', lang('Pages.Messages.created'));
            } catch (\Throwable $e) {
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }
        }

        return $this->form(null);
    }

    public function edit(int $id): string|RedirectResponse
    {
        if (($redirect = $this->guard('pages.edit')) !== null) {
            return $redirect;
        }

        $page = $this->pages()->find($id);
        if ($page === null) {
            return redirect()->to('/admin/pages')->with('error', lang('Pages.Errors.notFound'));
        }

        if ($this->request->getMethod() === 'POST') {
            try {
                $page = $this->pages()->update(
                    $id,
                    $this->formData(),
                    auth()->id() === null ? null : (int) auth()->id(),
                );

                return redirect()->to('/admin/pages/' . $page['id'] . '/edit')
                    ->with('message', lang('Pages.Messages.updated'));
            } catch (\Throwable $e) {
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }
        }

        return $this->form($page);
    }

    public function delete(int $id): RedirectResponse
    {
        if (($redirect = $this->guard('pages.delete')) !== null) {
            return $redirect;
        }

        $this->pages()->delete($id);

        return redirect()->to('/admin/pages')->with('message', lang('Pages.Messages.deleted'));
    }

    public function settings(): string|RedirectResponse
    {
        if (($redirect = $this->guard('pages.settings')) !== null) {
            return $redirect;
        }

        $settings = service('settings');

        if ($this->request->getMethod() === 'POST') {
            $defaultStatus = trim((string) $this->request->getPost('default_status'));

            if (! in_array($defaultStatus, ['draft', 'published'], true)) {
                return redirect()->back()->withInput()->with('error', lang('Pages.Errors.invalidStatus'));
            }

            $homeId = max(0, (int) $this->request->getPost('home_page_id'));
            $home = $homeId > 0 ? $this->pages()->find($homeId) : null;
            if ($homeId > 0 && ($home === null || $home['status'] !== 'published')) {
                return redirect()->back()->withInput()->with('error', lang('Pages.Errors.invalidHomePage'));
            }

            $settings->set('pages.default_status', $defaultStatus);
            $settings->set('pages.home_page_id', $homeId);

            return redirect()->to('/admin/pages/settings')
                ->with('message', lang('Pages.Messages.settingsSaved'));
        }

        $homePages = [];
        $offset = 0;
        do {
            $batch = $this->pages()->list('', 'published', 250, $offset);
            foreach ($batch as $candidate) {
                $homePages[] = ['id' => $candidate['id'], 'title' => $candidate['title']];
            }
            $offset += 250;
        } while (count($batch) === 250);

        return view('MCMS\\Modules\\Pages\\Views\\settings', [
            'title' => lang('Pages.Admin.settings'),
            'adminMenu' => $this->adminMenu(),
            'user' => auth()->user(),
            'defaultStatus' => (string) ($settings->get('pages.default_status') ?? 'draft'),
            'homePageId' => (int) ($settings->get('pages.home_page_id') ?? 0),
            'homePages' => $homePages,
        ]);
    }

    public function preview(): ResponseInterface
    {
        $id = max(0, (int) $this->request->getPost('content_id'));
        $permission = 'pages.' . ($id > 0 ? 'edit' : 'create');
        $this->response->setHeader('Cache-Control', 'private, no-store')
            ->setHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        if (! $this->can($permission)) {
            return $this->response->setStatusCode(403)->setJSON([
                'error' => lang('MCMS.errors.permissionDenied'), 'csrfHash' => csrf_hash(),
            ]);
        }
        try {
            $page = $this->pages()->preview($this->formData(), $id > 0 ? $id : null);
            $page['body'] = service('mcmsShortcodes')->render($page['body']);
            $siteName = (string) (setting('Core.siteName') ?? 'mCMS');
            $html = service('mcmsThemes')->render('page', [
                'page' => $page, 'siteName' => $siteName, 'isPreview' => true,
                'metaTitle' => (($page['seo_title'] ?? null) ?: $page['title']) . ' · ' . $siteName,
                'metaDescription' => $page['seo_description'] ?? $page['excerpt'] ?? null,
            ], 'MCMS\\Modules\\Pages\\Views\\show');
            return $this->response->setJSON(['html' => $html, 'csrfHash' => csrf_hash()]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(422)->setJSON(['error' => $e->getMessage(), 'csrfHash' => csrf_hash()]);
        }
    }

    public function media(): ResponseInterface
    {
        if (! $this->can('pages.create') && ! $this->can('pages.edit')) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'items' => [],
            ]);
        }

        $service = service('mcmsModuleServices')->get('media.library');
        if (! $service instanceof MediaLibraryInterface) {
            throw new RuntimeException(lang('Pages.Errors.mediaUnavailable'));
        }

        $query = trim((string) $this->request->getGet('q'));
        $items = array_values(array_filter(
            $service->list($query, 100),
            static fn (array $item): bool => (bool) ($item['is_image'] ?? false),
        ));

        return $this->response->setJSON([
            'success' => true,
            'items' => $items,
        ]);
    }

    private function form(?array $page): string
    {
        $defaultStatus = (string) (service('settings')->get('pages.default_status') ?? 'draft');

        return view('MCMS\\Modules\\Pages\\Views\\form', [
            'title' => $page === null ? lang('Pages.Admin.create') : lang('Pages.Admin.edit'),
            'adminMenu' => $this->adminMenu(),
            'user' => auth()->user(),
            'page' => $page,
            'defaultStatus' => in_array($defaultStatus, ['draft', 'published'], true)
                ? $defaultStatus
                : 'draft',
        ]);
    }

    private function formData(): array
    {
        return [
            'title' => (string) $this->request->getPost('title'),
            'slug' => (string) $this->request->getPost('slug'),
            'body' => (string) $this->request->getPost('body'),
            'status' => (string) $this->request->getPost('status'),
            'seo_title' => (string) $this->request->getPost('seo_title'),
            'seo_description' => (string) $this->request->getPost('seo_description'),
            'seo_keywords' => (string) $this->request->getPost('seo_keywords'),
            'tags' => (string) $this->request->getPost('tags'),
        ];
    }

    private function pages(): PageRepositoryInterface
    {
        $service = service('mcmsModuleServices')->get('pages.repository');

        if (! $service instanceof PageRepositoryInterface) {
            throw new RuntimeException(lang('Pages.Errors.notFound'));
        }

        return $service;
    }
}
