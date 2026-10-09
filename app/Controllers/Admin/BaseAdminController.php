<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;

abstract class BaseAdminController extends BaseController
{
    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        service('renderer')->setData(['settingsUrl' => fn (string $path): string => site_url(ltrim($this->settingsPath('/' . ltrim($path, '/')), '/'))]);
    }

    protected function settingsPath(string $path): string
    {
        return service('mcmsAdmin')->settingsPath($path, $this->request->getUri()->getPath());
    }

    protected function can(string $permission): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can($permission);
    }

    protected function guard(string $permission): ?RedirectResponse
    {
        if ($this->can($permission)) {
            return null;
        }

        return redirect()->to('/')->with('error', lang('MCMS.errors.permissionDenied'));
    }

    protected function adminMenu(): array
    {
        return array_values(array_filter(
            service('mcmsAdmin')->menu(),
            fn (array $item): bool => $item['permission'] === null || $this->can($item['permission'])
        ));
    }

    protected function adminWidgets(): array
    {
        return array_values(array_filter(
            service('mcmsAdmin')->widgets(),
            fn (array $widget): bool => $widget['permission'] === null || $this->can($widget['permission'])
        ));
    }
}
