<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;

abstract class BaseAdminController extends BaseController
{
    protected function can(string $permission): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can($permission);
    }

    protected function guard(string $permission): ?RedirectResponse
    {
        if ($this->can($permission)) {
            return null;
        }

        return redirect()->to('/')->with('error', 'You do not have permission to access that area.');
    }

    protected function adminMenu(): array
    {
        return array_values(array_filter(
            service('mcmsAdmin')->menu(),
            fn (array $item): bool => $item['permission'] === null || $this->can($item['permission'])
        ));
    }
}
