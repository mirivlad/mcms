<?php

declare(strict_types=1);

namespace MCMS\Modules\Captcha\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use MCMS\Modules\Captcha\Api\CaptchaInterface;

final class Image extends BaseController
{
    public function show(string $token): ResponseInterface
    {
        $this->response->setHeader('Cache-Control', 'no-store, private')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Content-Security-Policy', "default-src 'none'; sandbox");
        if (! service('throttler')->check('captcha-image-' . hash('sha256', $this->request->getIPAddress()), 60, 60)) {
            return $this->response->setStatusCode(429);
        }
        $service = service('mcmsModuleServices')->get('captcha.verifier');
        assert($service instanceof CaptchaInterface);
        $image = $service->image($token);
        if ($image === null) {
            return $this->response->setStatusCode(404);
        }
        return $this->response->setContentType('image/png')->setBody($image);
    }
}
