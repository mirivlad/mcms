<?php

declare(strict_types=1);

namespace MCMS\Modules\Media\Controllers;

use App\Controllers\Admin\BaseAdminController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use MCMS\Modules\Media\Api\MediaLibraryInterface;
use RuntimeException;

final class Admin extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if (($redirect = $this->guard('media.view')) !== null) {
            return $redirect;
        }

        $query = trim((string) $this->request->getGet('q'));
        $library = $this->library();

        return view('MCMS\Modules\Media\Views\index', [
            'title'     => lang('MCMS.media.title'),
            'adminMenu' => $this->adminMenu(),
            'user'      => auth()->user(),
            'query'     => $query,
            'items'     => $library->list($query),
            'count'     => $library->count($query),
            'canUpload' => $this->can('media.upload'),
            'canEdit'   => $this->can('media.edit'),
            'canDelete' => $this->can('media.delete'),
            'canSettings' => $this->can('media.settings'),
        ]);
    }

    public function upload(): RedirectResponse
    {
        if (($redirect = $this->guard('media.upload')) !== null) {
            return $redirect;
        }

        $file = $this->request->getFile('file');

        if ($file === null) {
            return redirect()->to('/admin/media')->with('error', lang('MCMS.errors.chooseUpload'));
        }

        try {
            $this->library()->store(
                $file,
                auth()->id() === null ? null : (int) auth()->id(),
                (string) $this->request->getPost('title'),
                (string) $this->request->getPost('alt_text'),
            );

            return redirect()->to('/admin/media')->with('message', lang('MCMS.messages.mediaUploaded'));
        } catch (\Throwable $e) {
            return redirect()->to('/admin/media')->with('error', $e->getMessage());
        }
    }

    public function updateMetadata(int $id): RedirectResponse
    {
        if (($redirect = $this->guard('media.edit')) !== null) {
            return $redirect;
        }

        try {
            $this->library()->updateMetadata(
                $id,
                (string) $this->request->getPost('title'),
                (string) $this->request->getPost('alt_text'),
            );

            return redirect()->to('/admin/media')->with('message', lang('MCMS.messages.mediaMetadataUpdated'));
        } catch (\Throwable $e) {
            return redirect()->to('/admin/media')->with('error', $e->getMessage());
        }
    }

    public function delete(int $id): RedirectResponse
    {
        if (($redirect = $this->guard('media.delete')) !== null) {
            return $redirect;
        }

        try {
            $this->library()->delete($id);

            return redirect()->to('/admin/media')->with('message', lang('MCMS.messages.mediaDeleted'));
        } catch (\Throwable $e) {
            return redirect()->to('/admin/media')->with('error', $e->getMessage());
        }
    }

    public function settings(): string|RedirectResponse
    {
        if (($redirect = $this->guard('media.settings')) !== null) {
            return $redirect;
        }

        $library = $this->library();
        $supported = $library->supportedMimeTypes();

        if ($this->request->getMethod() === 'POST') {
            $maxUploadMb = (int) $this->request->getPost('max_upload_mb');
            $maxMegapixels = (int) $this->request->getPost('max_image_megapixels');
            $allowed = array_values(array_intersect(
                array_keys($supported),
                array_filter((array) $this->request->getPost('allowed_mime_types'), 'is_string'),
            ));

            if ($maxUploadMb < 1 || $maxUploadMb > 64) {
                return redirect()->back()->withInput()->with('error', lang('MCMS.errors.uploadLimit'));
            }

            if ($maxMegapixels < 1 || $maxMegapixels > 200) {
                return redirect()->back()->withInput()->with('error', lang('MCMS.errors.imageLimit'));
            }

            if ($allowed === []) {
                return redirect()->back()->withInput()->with('error', lang('MCMS.errors.allowMediaType'));
            }

            $settings = service('settings');
            $settings->set('media.max_upload_mb', $maxUploadMb);
            $settings->set('media.max_image_megapixels', $maxMegapixels);
            $settings->set('media.allowed_mime_types', $allowed);

            return redirect()->to('/admin/media/settings')->with('message', lang('MCMS.messages.mediaSettingsSaved'));
        }

        return view('MCMS\Modules\Media\Views\settings', [
            'title'          => lang('MCMS.media.settings'),
            'adminMenu'      => $this->adminMenu(),
            'user'           => auth()->user(),
            'maxUploadMb'    => (int) (service('settings')->get('media.max_upload_mb') ?? 20),
            'maxMegapixels'  => (int) (service('settings')->get('media.max_image_megapixels') ?? 40),
            'allowed'        => (array) (service('settings')->get('media.allowed_mime_types') ?? []),
            'supported'      => $supported,
        ]);
    }

    public function apiFiles(): ResponseInterface
    {
        if (! $this->can('media.view')) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'items'   => [],
            ]);
        }

        $query = trim((string) $this->request->getGet('q'));

        return $this->response->setJSON([
            'success' => true,
            'items'   => $this->library()->list($query, 100),
        ]);
    }

    private function library(): MediaLibraryInterface
    {
        $service = service('mcmsModuleServices')->get('media.library');

        if (! $service instanceof MediaLibraryInterface) {
            throw new RuntimeException(lang('MCMS.errors.mediaUnavailable'));
        }

        return $service;
    }
}
