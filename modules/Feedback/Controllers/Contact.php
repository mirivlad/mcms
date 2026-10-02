<?php

declare(strict_types=1);

namespace MCMS\Modules\Feedback\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;

final class Contact extends BaseController
{
    public function index(): string|RedirectResponse|ResponseInterface
    {
        $error = null;
        $values = [];
        if ($this->request->getMethod() === 'POST') {
            if (! service('throttler')->check('feedback-' . hash('sha256', $this->request->getIPAddress()), 5, 60)) {
                return $this->response->setStatusCode(429)->setBody(lang('Feedback.Errors.tooMany'));
            }
            if (trim((string) $this->request->getPost('website')) !== '') {
                return redirect()->to('/contact')->with('feedback_sent', true);
            }
            $values = ['name' => $this->request->getPost('name'), 'email' => $this->request->getPost('email'), 'message' => $this->request->getPost('message')];
            try {
                service('mcmsModuleServices')->get('feedback.repository')->create($values);
                return redirect()->to('/contact')->with('feedback_sent', true);
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }
        return service('mcmsThemes')->render('feedback.form', [
            'metaTitle' => lang('Feedback.Public.title'), 'values' => $values, 'error' => $error,
            'sent' => (bool) session()->getFlashdata('feedback_sent'),
        ], 'MCMS\\Modules\\Feedback\\Views\\public_form');
    }
}
