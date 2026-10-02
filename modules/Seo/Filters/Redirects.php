<?php

declare(strict_types=1);

namespace MCMS\Modules\Seo\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class Redirects implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return null;
        }
        $path = $request->getUri()->getPath();
        $base = rtrim((string) parse_url(config('App')->baseURL, PHP_URL_PATH), '/');
        if ($base !== '' && str_starts_with($path, $base . '/')) {
            $path = substr($path, strlen($base));
        }
        $target = service('mcmsModuleServices')->get('seo.redirects')->target($path);
        return $target === null ? null : redirect()->to(site_url(ltrim($target, '/')), 301);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
