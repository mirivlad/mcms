<?php

declare(strict_types=1);

namespace MCMS\Modules\Galleries\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use MCMS\Modules\Galleries\Api\GalleryRepositoryInterface;
use RuntimeException;

final class Galleries extends BaseController
{
    public function asset(string $name): ResponseInterface
    {
        $types = ['display.css' => 'text/css; charset=UTF-8', 'display.js' => 'text/javascript; charset=UTF-8'];
        if (! isset($types[$name])) {
            throw PageNotFoundException::forPageNotFound();
        }
        return $this->response->setHeader('Content-Type', $types[$name])
            ->setHeader('Cache-Control', 'private, max-age=86400')
            ->setBody(file_get_contents(dirname(__DIR__) . '/assets/' . $name));
    }

    public function index(): string
    {
        $galleries = $this->galleries();
        $perPage = 12;
        $page = max(1, (int) $this->request->getGet('page'));
        $count = $galleries->countPublished();
        $lastPage = max(1, (int) ceil($count / $perPage));
        $page = min($page, $lastPage);

        $siteName = (string) (setting('Core.siteName') ?? 'mCMS');

        return service('mcmsThemes')->render(
            'galleries.index',
            [
                'navigationResource' => ['type' => 'galleries.archive', 'id' => 'index'],
                'albums' => $galleries->listPublished('', $perPage, ($page - 1) * $perPage),
                'page' => $page,
                'lastPage' => $lastPage,
                'count' => $count,
                'siteName' => $siteName,
                'metaTitle' => lang('Galleries.Public.title') . ' · ' . $siteName,
            ],
            'MCMS\\Modules\\Galleries\\Views\\public_index',
        );
    }

    public function show(string $slug): string
    {
        $album = $this->galleries()->findPublishedBySlug($slug);
        if ($album === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $siteName = (string) (setting('Core.siteName') ?? 'mCMS');
        $title = (string) $album['title'];

        return service('mcmsThemes')->render(
            'galleries.album',
            [
                'navigationResource' => ['type' => 'galleries.album', 'id' => (string) $album['id']],
                'album' => $album,
                'siteName' => $siteName,
                'metaTitle' => $title . ' · ' . $siteName,
                'metaDescription' => $album['description'],
            ],
            'MCMS\\Modules\\Galleries\\Views\\public_album',
        );
    }

    private function galleries(): GalleryRepositoryInterface
    {
        $service = service('mcmsModuleServices')->get('galleries.repository');

        if (! $service instanceof GalleryRepositoryInterface) {
            throw new RuntimeException(lang('Galleries.Errors.serviceUnavailable'));
        }

        return $service;
    }
}
