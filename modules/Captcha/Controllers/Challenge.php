<?php

declare(strict_types=1);

namespace MCMS\Modules\Captcha\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

final class Challenge extends BaseController
{
    public function issue(): ResponseInterface
    {
        $purpose = (string) $this->request->getPost('purpose');
        $type = (string) $this->request->getPost('target_type');
        $id = (string) $this->request->getPost('target_id');
        $scope = [
            'comments.create' => ['comments.repository', 'comments.allow_guests'],
            'ratings.vote' => ['ratings.repository', 'ratings.allow_guests'],
        ][$purpose] ?? null;
        $services = service('mcmsModuleServices');
        if ($scope === null || ! $services->has($scope[0])
            || $services->get($scope[0])->publicTarget($type, $id) === null) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false]);
        }

        session();
        $authenticated = auth()->loggedIn();
        if (! $authenticated && ! (bool) setting($scope[1])) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false]);
        }
        $captcha = $services->get('captcha.verifier');
        $required = method_exists($captcha, 'required')
            ? $captcha->required($purpose, $authenticated) : ! $authenticated;
        if (! $required) {
            return $this->response->setHeader('Cache-Control', 'no-store')->setJSON([
                'success' => true,
                'required' => false,
                'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()],
            ]);
        }

        try {
            $challenge = $captcha->issue($purpose, session_id());
        } catch (RuntimeException) {
            return $this->response->setStatusCode(429)->setHeader('Cache-Control', 'no-store')->setJSON([
                'success' => false,
                'message' => lang('Captcha.Public.unavailable'),
                'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()],
            ]);
        }

        return $this->response->setHeader('Cache-Control', 'no-store')->setJSON([
            'success' => true,
            'required' => true,
            'html' => view('MCMS\\Modules\\Captcha\\Views\\widget', ['challenge' => $challenge]),
            'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()],
        ]);
    }
}
