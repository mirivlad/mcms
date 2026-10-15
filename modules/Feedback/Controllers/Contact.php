<?php

declare(strict_types=1);

namespace MCMS\Modules\Feedback\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use RuntimeException;

final class Contact extends BaseController
{
    public function index(): string|RedirectResponse|ResponseInterface
    {
        $error = null;
        $values = [];
        $services = service('mcmsModuleServices');
        $purpose = 'feedback.contact';
        $captcha = $services->has('captcha.verifier') ? $services->get('captcha.verifier') : null;
        if ($captcha !== null && (method_exists($captcha, 'required') ? ! $captcha->required($purpose, auth()->loggedIn()) : auth()->loggedIn())) {
            $captcha = null;
        }
        session();
        $sessionId = session_id();
        if ($this->request->getMethod() === 'POST') {
            if (! service('throttler')->check('feedback-' . hash('sha256', $this->request->getIPAddress()), 5, 60)) {
                return $this->response->setStatusCode(429)->setBody(lang('Feedback.Errors.tooMany'));
            }
            if (trim((string) $this->request->getPost('website')) !== '') {
                return redirect()->to('/contact')->with('feedback_sent', true);
            }
            $values = ['name' => $this->request->getPost('name'), 'email' => $this->request->getPost('email'), 'message' => $this->request->getPost('message')];
            if ($captcha !== null && ! $captcha->verify(
                $purpose, $sessionId,
                (string) $this->request->getPost('captcha_token'),
                (string) $this->request->getPost('captcha_answer'),
            )) {
                $error = lang('Captcha.Public.invalid');
            } else {
                try {
                    $services->get('feedback.repository')->create($values);
                    return redirect()->to('/contact')->with('feedback_sent', true);
                } catch (InvalidArgumentException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        $challenge = null;
        if ($captcha !== null) {
            try {
                $challenge = $captcha->issue($purpose, $sessionId);
            } catch (RuntimeException) {
                $error = lang('Captcha.Public.unavailable');
            }
        }

        return service('mcmsThemes')->render('feedback.form', [
            'metaTitle' => lang('Feedback.Public.title'), 'values' => $values, 'error' => $error,
            'sent' => (bool) session()->getFlashdata('feedback_sent'),
            'captchaChallenge' => $challenge,
            'captchaRequired' => $captcha !== null,
        ], 'MCMS\\Modules\\Feedback\\Views\\public_form');
    }
}
