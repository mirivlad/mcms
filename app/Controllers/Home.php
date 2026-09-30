<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;

class Home extends BaseController
{
    public function index(): string|RedirectResponse
    {
        if (! service('mcmsInstaller')->isInstalled()) {
            return redirect()->to('/install');
        }

        return view('home/index');
    }
}
