<?php

declare(strict_types=1);

namespace MCMS\Modules\News\Controllers;

use App\Controllers\Admin\BaseAdminController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use MCMS\Modules\Media\Api\MediaLibraryInterface;
use MCMS\Modules\News\Api\NewsRepositoryInterface;
use RuntimeException;
use MCMS\Core\Content\ContentWorkflow;
use MCMS\Core\Content\ContentWorkflowActions;

final class Admin extends BaseAdminController
{
    use ContentWorkflowActions;

    public function index(): string|RedirectResponse
    {
        if (($redirect = $this->guard('news.view')) !== null) {
            return $redirect;
        }

        $query = trim((string) $this->request->getGet('q'));
        $status = trim((string) $this->request->getGet('status'));
        $status = in_array($status, ['draft', 'published'], true) ? $status : null;
        $news = $this->news();
        $perPage = 50;
        $count = $news->count($query, $status);
        $lastPage = max(1, (int) ceil($count / $perPage));
        $page = max(1, min((int) $this->request->getGet('page'), $lastPage));

        return view('MCMS\\Modules\\News\\Views\\index', [
            'title' => lang('News.Admin.title'),
            'adminMenu' => $this->adminMenu(),
            'user' => auth()->user(),
            'articles' => $news->list($query, $status, $perPage, ($page - 1) * $perPage),
            'count' => $count,
            'query' => $query,
            'status' => $status,
            'page' => $page,
            'lastPage' => $lastPage,
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

                $this->workflow()->discardDraft((int) auth()->id(), 0);

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

                $this->workflow()->discardDraft((int) auth()->id(), $id);

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

    public function preview(): ResponseInterface
    {
        $id = max(0, (int) $this->request->getPost('content_id'));
        $permission = 'news.' . ($id > 0 ? 'edit' : 'create');
        $this->response->setHeader('Cache-Control', 'private, no-store')
            ->setHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        if (! $this->can($permission)) {
            return $this->response->setStatusCode(403)->setJSON([
                'error' => lang('MCMS.errors.permissionDenied'), 'csrfHash' => csrf_hash(),
            ]);
        }
        try {
            $article = $this->news()->preview($this->formData(), $id > 0 ? $id : null);
            $article['published_at'] ??= date('Y-m-d H:i:s');
            $article['body'] = service('mcmsShortcodes')->render($article['body']);
            $siteName = (string) (setting('Core.siteName') ?? 'mCMS');
            $html = service('mcmsThemes')->render('news.article', [
                'article' => $article, 'siteName' => $siteName, 'isPreview' => true,
                'metaTitle' => (($article['seo_title'] ?? null) ?: $article['title']) . ' · ' . $siteName,
                'metaDescription' => $article['seo_description'] ?? $article['excerpt'] ?? null,
            ], 'MCMS\\Modules\\News\\Views\\public_article');
            return $this->response->setJSON(['html' => $html, 'csrfHash' => csrf_hash()]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(422)->setJSON(['error' => $e->getMessage(), 'csrfHash' => csrf_hash()]);
        }
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
        ]);
    }

    private function workflowDraftData(): array
    {
        $data = $this->formData();
        $media = service('mcmsModuleServices')->get('media.library');
        $image = $media instanceof MediaLibraryInterface ? $media->find((int) $data['featured_media_id']) : null;
        $data['featured_image_url'] = $image === null ? null : $media->url($image);
        return $data;
    }

    private function workflow(): ContentWorkflow
    {
        return new ContentWorkflow(db_connect(), 'news');
    }

    private function workflowPermission(int $id): bool
    {
        return $this->can('news.' . ($id > 0 ? 'edit' : 'create'));
    }

    private function workflowContent(int $id): ?array
    {
        return $this->news()->find($id);
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
            'seo_keywords' => (string) $this->request->getPost('seo_keywords'),
            'tags' => (string) $this->request->getPost('tags'),
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
