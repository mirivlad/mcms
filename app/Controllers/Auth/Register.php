<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Controllers\RegisterController;
use RuntimeException;

/**
 * Public registration uses Shield's validation, persistence, sessions and
 * default 'user' group, but makes CAPTCHA mandatory before user creation.
 */
final class Register extends RegisterController
{
    public function registerView(): string|RedirectResponse
    {
        if (auth()->loggedIn()) {
            return redirect()->to('/account');
        }

        if (! (bool) setting('Auth.allowRegistration')) {
            return redirect()->to('/login')->with('error', lang('MCMS.account.registrationClosed'));
        }

        $captcha = $this->captcha();
        if ($captcha === null) {
            return $this->renderRegistration(['challenge' => null, 'captchaUnavailable' => true]);
        }

        session();
        try {
            $challenge = $captcha->issue('auth.register', session_id());
        } catch (RuntimeException) {
            $challenge = null;
        }

        return $this->renderRegistration([
            'challenge' => $challenge,
            'captchaUnavailable' => $challenge === null,
        ]);
    }

    public function registerAction(): RedirectResponse
    {
        if (! (bool) setting('Auth.allowRegistration')) {
            return redirect()->to('/login')->with('error', lang('MCMS.account.registrationClosed'));
        }
        if (! service('throttler')->check(
            'public-register-' . hash('sha256', $this->request->getIPAddress()), 4, 3600
        )) {
            return redirect()->to('/register')->with('error', lang('MCMS.account.tooManyAttempts'));
        }

        session();
        $captcha = $this->captcha();
        if ($captcha === null || ! $captcha->verify(
            'auth.register', session_id(),
            (string) $this->request->getPost('captcha_token'),
            (string) $this->request->getPost('captcha_answer'),
        )) {
            return redirect()->to('/register')->withInput()
                ->with('error', lang('MCMS.account.captchaInvalid'));
        }

        // Shield performs its standard validation, creates the user in group
        // "user", activates it and completes the authenticated session.
        return parent::registerAction();
    }

    private function renderRegistration(array $data): string
    {
        $title = lang('MCMS.account.register');
        return service('mcmsThemes')->render('account.register', [
            ...$data,
            'accountPageTitle' => $title,
            'metaTitle' => $title . ' · ' . (string) (setting('Core.siteName') ?? 'mCMS'),
            'metaRobots' => 'noindex, nofollow',
        ], 'auth/register');
    }

    private function captcha(): ?object
    {
        $services = service('mcmsModuleServices');
        return $services->has('captcha.verifier') ? $services->get('captcha.verifier') : null;
    }
}
