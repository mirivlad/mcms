<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use MCMS\Core\Packages\ZipPackage;
use MCMS\Core\Themes\ThemePackages;

final class ThemeAssets extends BaseController
{
    public function show(string $id, string $version, string ...$segments): ResponseInterface
    {
        $path = implode('/', $segments);
        $theme = service('mcmsThemes')->find($id);
        $mime = ThemePackages::ASSET_TYPES[pathinfo($path, PATHINFO_EXTENSION)] ?? null;
        try {
            ZipPackage::validatePath($path);
        } catch (\RuntimeException) {
            throw PageNotFoundException::forPageNotFound();
        }
        $root = $theme === null ? false : realpath($theme->path . '/assets');
        $file = $root === false ? false : realpath($root . '/' . $path);
        if ($theme === null || ! $theme->external || $theme->version !== $version || $mime === null
            || $root === false || $file === false || ! str_starts_with($file, $root . DIRECTORY_SEPARATOR) || ! is_file($file)) {
            throw PageNotFoundException::forPageNotFound();
        }
        $content = file_get_contents($file);
        if ($content === false) {
            throw PageNotFoundException::forPageNotFound();
        }
        $etag = '"' . hash('sha256', $content) . '"';
        $this->response->setHeader('Content-Type', $mime)
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'public, max-age=31536000, immutable')
            ->setHeader('ETag', $etag);
        if ($mime === 'image/svg+xml') {
            $this->response->setHeader('Content-Security-Policy', "sandbox; default-src 'none'; style-src 'unsafe-inline'");
        }
        if ($this->request->getHeaderLine('If-None-Match') === $etag) {
            return $this->response->setStatusCode(304)->setBody('');
        }
        return $this->response->setBody($content);
    }
}
