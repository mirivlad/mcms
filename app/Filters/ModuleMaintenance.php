<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class ModuleMaintenance implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $flag = service('mcmsModuleRuntime')->maintenance();
        if ($flag === null) {
            return null;
        }
        $path = trim($request->getUri()->getPath(), '/');
        $method = strtoupper($request->getMethod());
        $id = is_string($flag['id'] ?? null) ? $flag['id'] : '';
        // Recovery is Core-only. All public/business writes remain blocked.
        if (($method === 'GET' && in_array($path, ['_health', 'admin/modules', 'login', 'logout'], true))
            || ($method === 'POST' && $path === 'login')
            || ($method === 'GET' && in_array($path, ['admin/modules/' . $id . '/update', 'admin/modules/' . $id . '/update/snapshot'], true))
            || ($method === 'POST' && $path === 'admin/modules/' . $id . '/update/restore')) {
            return null;
        }
        (new SiteLocale())->before($request);
        return service('response')->setStatusCode(503)->setHeader('Retry-After', '60')
            ->setHeader('Cache-Control', 'no-store')->setBody(view('maintenance'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
