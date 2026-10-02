<?php

declare(strict_types=1);

namespace MCMS\Modules\Galleries\Controllers;

use App\Controllers\Admin\BaseAdminController;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use MCMS\Modules\Galleries\Api\GalleryRepositoryInterface;
use MCMS\Modules\Media\Api\MediaLibraryInterface;

final class Admin extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if (($redirect = $this->guard('galleries.view')) !== null) {
            return $redirect;
        }
        $query = trim((string) $this->request->getGet('q'));
        $status = $this->request->getGet('status');
        $status = in_array($status, ['draft', 'published'], true) ? $status : null;
        $count = $this->repository()->count($query, $status);
        $lastPage = max(1, (int) ceil($count / 30));
        $page = max(1, min($lastPage, (int) $this->request->getGet('page')));
        return view('MCMS\\Modules\\Galleries\\Views\\index', [
            'adminMenu' => $this->adminMenu(), 'user' => auth()->user(),
            'albums' => $this->repository()->list($query, $status, 30, ($page - 1) * 30),
            'query' => $query, 'status' => $status, 'count' => $count, 'page' => $page, 'lastPage' => $lastPage,
            'canCreate' => $this->can('galleries.create'), 'canEdit' => $this->can('galleries.edit'), 'canDelete' => $this->can('galleries.delete'),
        ]);
    }

    public function create(): string|RedirectResponse
    {
        if (($redirect = $this->guard('galleries.create')) !== null) {
            return $redirect;
        }
        return $this->form(null);
    }

    public function edit(int $id): string|RedirectResponse
    {
        if (($redirect = $this->guard('galleries.edit')) !== null) {
            return $redirect;
        }
        $album = $this->repository()->find($id);
        if ($album === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        return $this->form($album);
    }

    public function delete(int $id): RedirectResponse
    {
        if (($redirect = $this->guard('galleries.delete')) !== null) {
            return $redirect;
        }
        $this->repository()->delete($id);
        return redirect()->to('/admin/galleries')->with('message', lang('Galleries.Messages.deleted'));
    }

    public function media(): ResponseInterface
    {
        if (! $this->can('galleries.create') && ! $this->can('galleries.edit')) {
            return $this->response->setStatusCode(403)->setJSON(['items' => []]);
        }
        $media = $this->mediaLibrary();
        $query = trim((string) $this->request->getGet('q'));
        $count = $media->count($query, true);
        $lastPage = max(1, (int) ceil($count / 30));
        $page = max(1, min($lastPage, (int) $this->request->getGet('page')));
        return $this->response->setJSON(['items' => $media->list($query, 30, ($page - 1) * 30, true), 'page' => $page, 'lastPage' => $lastPage]);
    }

    private function form(?array $album): string|RedirectResponse
    {
        $data = $this->request->getMethod() === 'POST' ? [
            'title' => $this->request->getPost('title'), 'slug' => $this->request->getPost('slug'),
            'description' => $this->request->getPost('description'), 'status' => $this->request->getPost('status'),
            'cover_media_id' => $this->request->getPost('cover_media_id'),
            'media_ids' => (array) $this->request->getPost('media_ids'), 'captions' => (array) $this->request->getPost('captions'), 'positions' => (array) $this->request->getPost('positions'),
        ] : null;
        $error = null;
        if ($data !== null) {
            try {
                $saved = $album === null ? $this->repository()->create($data, (int) auth()->id())
                    : $this->repository()->update($album['id'], $data, (int) auth()->id());
                return redirect()->to('/admin/galleries/' . $saved['id'] . '/edit')->with('message', lang('Galleries.Messages.saved'));
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }
        $selected = $album['images'] ?? [];
        if ($data !== null) {
            $selected = [];
            foreach (array_unique(array_map('intval', $data['media_ids'])) as $index => $id) {
                $selected[] = ['media_id' => $id, 'caption' => (string) ($data['captions'][$id] ?? ''),
                    'position' => (int) ($data['positions'][$id] ?? $index), 'media' => $this->mediaLibrary()->find($id)];
            }
        }
        return view('MCMS\\Modules\\Galleries\\Views\\form', [
            'adminMenu' => $this->adminMenu(), 'user' => auth()->user(), 'album' => $album,
            'values' => $data ?? $album ?? [], 'selected' => $selected, 'error' => $error,
        ]);
    }

    private function repository(): GalleryRepositoryInterface
    {
        return service('mcmsModuleServices')->get('galleries.repository');
    }

    private function mediaLibrary(): MediaLibraryInterface
    {
        return service('mcmsModuleServices')->get('media.library');
    }
}
