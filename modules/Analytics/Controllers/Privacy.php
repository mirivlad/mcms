<?php

declare(strict_types=1);

namespace MCMS\Modules\Analytics\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use MCMS\Modules\Analytics\Privacy\Consent;

final class Privacy extends Controller
{
    public function index(): string
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        return view('MCMS\\Modules\\Analytics\\Views\\privacy', [
            'choice' => $this->request->getCookie(Consent::COOKIE),
        ]);
    }

    public function save(): ResponseInterface
    {
        $choice = $this->request->getPost('choice');
        if (! is_string($choice) || ! in_array($choice, ['yes', 'no'], true)) {
            return $this->response->setStatusCode(422)->setBody('Invalid privacy preference');
        }
        $base = rtrim((string) parse_url(config('App')->baseURL, PHP_URL_PATH), '/');
        $path = ($base ?: '') . '/';
        $secure = config('Cookie')->secure;
        $this->response->setCookie(Consent::COOKIE, $choice, 365 * 86400, '', $path, '', $secure, true, 'Lax');
        if ($choice === 'no') {
            foreach (['visitor', 'visit'] as $name) {
                // Clear identifiers on opt-out; previously retained aggregated
                // rows expire under the regular 90-day retention policy.
                $this->response->setCookie('mcms_analytics_' . $name, '', -3600, '', $path, '', $secure, true, 'Lax');
            }
        }
        return $this->response->setStatusCode(303)->setHeader('Location', site_url('analytics/privacy'));
    }
}
