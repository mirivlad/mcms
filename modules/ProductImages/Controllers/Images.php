<?php

declare(strict_types=1);

namespace MCMS\Modules\ProductImages\Controllers;

use App\Controllers\Admin\BaseAdminController;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use MCMS\Modules\ProductImages\Api\ProductImagesInterface;
use RuntimeException;

final class Images extends BaseAdminController
{
    public function asset(string $name): ResponseInterface
    {
        if (! $this->can('product-images.manage')) {
            return $this->response->setStatusCode(403);
        }
        $types = ['editor.js' => 'text/javascript; charset=UTF-8', 'editor.css' => 'text/css; charset=UTF-8'];
        if (! isset($types[$name])) {
            throw PageNotFoundException::forPageNotFound();
        }
        return $this->response->setHeader('Content-Type', $types[$name])->setBody(file_get_contents(dirname(__DIR__) . '/assets/' . $name));
    }

    public function upload(string $set): ResponseInterface
    {
        return $this->change($set, function (ProductImagesInterface $images) use ($set): void {
            $file = $this->request->getFile('file');
            if ($file === null || ! $file->isValid()) {
                throw new RuntimeException(lang('ProductImages.invalidFile'));
            }
            $images->upload($set, $file->getTempName(), $file->getClientName(), max(0, (int) $this->request->getPost('replace_id')) ?: null);
        });
    }

    public function crop(string $set, int $id): ResponseInterface
    {
        return $this->change($set, fn (ProductImagesInterface $images) => $images->crop($set, $id, (array) $this->request->getPost()));
    }

    public function arrange(string $set): ResponseInterface
    {
        return $this->change($set, fn (ProductImagesInterface $images) => $images->arrange($set,
            (array) $this->request->getPost('ids'), (int) $this->request->getPost('main_id')));
    }

    public function remove(string $set, int $id): ResponseInterface
    {
        return $this->change($set, fn (ProductImagesInterface $images) => $images->remove($set, $id));
    }

    public function file(string $set, int $id, string $size): ResponseInterface
    {
        if (! $this->can('product-images.manage')) {
            return $this->response->setStatusCode(403);
        }
        try {
            $this->library()->authorize($set, (int) auth()->id());
            $file = $this->library()->file($set, $id, $size);
        } catch (\Throwable) {
            $file = null;
        }
        if ($file === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        return $this->response->setHeader('Content-Type', $file['mime'])
            ->setHeader('Cache-Control', 'private, no-store')->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody(file_get_contents($file['path']));
    }

    private function change(string $set, callable $action): ResponseInterface
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        if (! $this->can('product-images.manage')) {
            return $this->response->setStatusCode(403)->setJSON(['error' => lang('ProductImages.denied'), 'csrfHash' => csrf_hash()]);
        }
        try {
            $images = $this->library();
            $images->authorize($set, (int) auth()->id());
            $action($images);
            return $this->response->setJSON(['items' => $images->images($set), 'csrfHash' => csrf_hash()]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(422)->setJSON(['error' => $e->getMessage(), 'csrfHash' => csrf_hash()]);
        }
    }

    private function library(): ProductImagesInterface
    {
        return service('mcmsModuleServices')->get('product-images.library');
    }
}
