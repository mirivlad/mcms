<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use CodeIgniter\CodeIgniter;
use CodeIgniter\HTTP\RedirectResponse;

final class System extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if (($redirect = $this->guard('core.system')) !== null) {
            return $redirect;
        }

        $manager = service('mcmsModules');
        $states = $manager->states();

        return view('admin/system/index', [
            'adminMenu'      => $this->adminMenu(),
            'user'           => auth()->user(),
            'phpVersion'     => PHP_VERSION,
            'ciVersion'      => CodeIgniter::CI_VERSION,
            'environment'    => ENVIRONMENT,
            'availableCount' => count($manager->catalog()),
            'enabledCount'   => count(array_filter($states, static fn (array $state): bool => (int) $state['enabled'] === 1)),
        ]);
    }
}
