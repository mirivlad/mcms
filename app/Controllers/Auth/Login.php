<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Controllers\LoginController;

/**
 * Shield owns login POST/session logic. Only the public GET view is themed.
 */
final class Login extends LoginController
{
    public function loginView(): string|RedirectResponse
    {
        if (auth()->loggedIn()) {
            return redirect()->to(config('Auth')->loginRedirect())->withCookies();
        }
        if (auth('session')->getAuthenticator()->hasAction()) {
            return redirect()->route('auth-action-show');
        }

        $title = lang('MCMS.account.login');

        return service('mcmsThemes')->render('account.login', [
            'accountPageTitle' => $title,
            'metaTitle' => $title . ' · ' . (string) (setting('Core.siteName') ?? 'mCMS'),
            'metaRobots' => 'noindex, nofollow',
        ], 'auth/login');
    }
}
