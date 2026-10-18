<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/** Block banned sessions even after a prior successful login. */
final class AccountActive implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Do not initialize authentication against an unconfigured installer DB.
        if (\MCMS\Core\Install\SharedHostSetup::enabled()
            && ! \MCMS\Core\Install\SharedHostSetup::configured()) return null;
        if (in_array(trim($request->getUri()->getPath(), '/'), ['install', '_health'], true)) return null;
        if (! auth('session')->loggedIn()) return null;
        $account = auth('session')->user();
        if ($account === null || ! $account->isBanned()) return null;
        auth('session')->logout();
        return redirect()->to('/login')->with('error', lang('MCMS.users.accountInactive'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
