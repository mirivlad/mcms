<?php

declare(strict_types=1);

namespace MCMS\Modules\News\Controllers;

use App\Controllers\Admin\BaseAdminController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use MCMS\Modules\Media\Api\MediaLibraryInterface;
use MCMS\Modules\News\Api\NewsRepositoryInterface;
use RuntimeException;

final class Admin extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if (($redirect = $this->guard('news.view')) !== null) {
            return $redirect;
        }

        $query = trim((string) $this->request->getGet('q'));
        $status = trim((string) $this->request->getGet('status'));
        $status = in_array($status, ['draft', 'published'], true) ? $status : null;
        $news = $this->news();

        return view('MCMS\\Modules\\News\\Views\\index', [
            'title' => lang('News.Admin.title'),
            'adminMenu' => $this->adminMenu(),
            'user' => auth()->user(),
            'articles' => $news->list($query, $status),
            'count' => $news->count($query, $status),
            'query' => $query,
            'status' => $status,
            'canCreate' => $this->can('news.create'),
            'canEdit' => $this->can('news.edit'),
            'canDelete' => $this->can('news.delete'),
        ]);
    }

    public function create(): string|RedirectResponse
    {
        if (($redirect = $this->guard('news.create')) !== null) {
            return $redirect;
        }

        if ($this->request->getMethod() === 'POST') {
            try {
                $article = $this->news()->create(
                    $this->formData(),
                    auth()->id() === null ? null : (int) auth()->id(),
                );

                return redirect()->to('/admin/news/' . $article['id'] . '/edit')
                    ->with('message', lang('News.Messages.created'));
            } catch (\Throwable $e) {
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }
        }

        return $this->form(null);
    }

    public function edit(int $id): string|RedirectResponse
    {
        if (($redirect = $this->guard('news.edit')) !== null) {
            return $redirect;
        }

        $article = $this->news()->find($id);
        if ($article === null) {
            return redirect()->to('/admin/news')->with('error', lang('News.Errors.notFound'));
        }

        if ($this->request->getMethod() === 'POST') {
            try {
                $article = $this->news()->update(
                    $id,
                    $this->formData(),
                    auth()->id() === null ? null : (int) auth()->id(),
                );

                return redirect()->to('/admin/news/' . $article['id'] . '/edit')
                    ->with('message', lang('News.Messages.updated'));
            } catch (\Throwable $e) {
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }
        }

        return $this->form($article);
    }

    public function delete(int $id): RedirectResponse
    {
        if (($redirect = $this->guard('news.delete')) !== null) {
            return $redirect;
        }

        $this->news()->delete($id);

        return redirect()->to('/admin/news')->with('message', lang('News.Messages.deleted'));
    }

    public function media(): ResponseInterface
    {
        if (! $this->can('news.create') && ! $this->can('news.edit')) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'items' => [],
            ]);
        }

        $query = trim((string) $this->request->getGet('q'));
        $items = array_values(array_filter(
            $this->mediaLibrary()->list($query, 100),
            static fn (array $item): bool => (bool) ($item['is_image'] ?? false),
        ));

        return $this->response->setJSON([
            'success' => true,
            'items' => $items,
        ]);
    }

    private function form(?array $article): string
    {
        $defaultStatus = (string) (service('settings')->get('news.default_status') ?? 'draft');

        return view('MCMS\\Modules\\News\\Views\\form', [
            'title' => $article === null ? lang('News.Admin.create') : lang('News.Admin.edit'),
            'adminMenu' => $this->adminMenu(),
            'user' => auth()->user(),
            'article' => $article,
            'defaultStatus' => in_array($defaultStatus, ['draft', 'published'], true)
                ? $defaultStatus
                : 'draft',
            'mediaImages' => array_values(array_filter(
                $this->mediaLibrary()->list('', 100),
                static fn (array $item): bool => (bool) ($item['is_image'] ?? false),
            )),
        ]);
    }

    private function formData(): array
    {
        return [
            'title' => (string) $this->request->getPost('title'),
            'slug' => (string) $this->request->getPost('slug'),
            'excerpt' => (string) $this->request->getPost('excerpt'),
            'body' => (string) $this->request->getPost('body'),
            'status' => (string) $this->request->getPost('status'),
            'published_at' => (string) $this->request->getPost('published_at'),
            'featured_media_id' => (string) $this->request->getPost('featured_media_id'),
            'seo_title' => (string) $this->request->getPost('seo_title'),
            'seo_description' => (string) $this->request->getPost('seo_description'),
        ];
    }

    private function news(): NewsRepositoryInterface
    {
        $service = service('mcmsModuleServices')->get('news.repository');

        if (! $service instanceof NewsRepositoryInterface) {
            throw new RuntimeException(lang('News.Errors.serviceUnavailable'));
        }

        return $service;
    }

    private function mediaLibrary(): MediaLibraryInterface
    {
        $service = service('mcmsModuleServices')->get('media.library');

        if (! $service instanceof MediaLibraryInterface) {
            throw new RuntimeException(lang('News.Errors.mediaUnavailable'));
        }

        return $service;
    }
}
