<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Apache/shared-host fallback for the nginx media alias.
 * Only canonical MediaLibrary-generated names may be served.
 */
final class PublicMedia extends BaseController
{
    private const TYPES = [
        'jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        'gif' => 'image/gif', 'pdf' => 'application/pdf',
    ];

    public static function validPath(string $path): bool
    {
        return preg_match('#\A[0-9]{4}/(?:0[1-9]|1[0-2])/[a-f0-9]{32}\.(?:jpg|png|webp|gif|pdf)(?:\.thumb\.png)?\z#D', $path) === 1;
    }

    public function show(string ...$segments): ResponseInterface
    {
        $path = implode('/', $segments);
        if (! self::validPath($path)) {
            throw PageNotFoundException::forPageNotFound();
        }
        $root = realpath(WRITEPATH . 'modules-data/data/media');
        $filename = $root === false ? false : realpath($root . '/' . $path);
        if ($root === false || $filename === false
            || ! str_starts_with($filename, $root . DIRECTORY_SEPARATOR)
            || ! is_file($filename) || ! is_readable($filename)) {
            throw PageNotFoundException::forPageNotFound();
        }
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime = self::TYPES[$extension] ?? null;
        if ($mime === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $content = file_get_contents($filename);
        if ($content === false) {
            throw PageNotFoundException::forPageNotFound();
        }
        $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'public, max-age=3600');
        if ($mime === 'application/pdf') {
            $this->response->setHeader('Content-Security-Policy', "sandbox; default-src 'none'");
        }
        return $this->response->setBody($content);
    }
}
