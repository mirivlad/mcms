<?php

declare(strict_types=1);

namespace MCMS\Modules\Analytics\Controllers;

use App\Controllers\Admin\BaseAdminController;
use CodeIgniter\HTTP\RedirectResponse;
use InvalidArgumentException;

final class Admin extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if (($redirect = $this->guard('analytics.view')) !== null) {
            return $redirect;
        }
        $this->response->setHeader('Cache-Control', 'private, no-store');
        $filters = [];
        foreach (['from' => gmdate('Y-m-d', time() - 29 * 86400), 'to' => gmdate('Y-m-d')] as $name => $default) {
            $value = $this->request->getGet($name) ?? $default;
            $filters[$name] = is_string($value) ? $value : 'invalid';
        }
        $error = null;
        $report = [];
        try {
            $report = service('mcmsModuleServices')->get('analytics.statistics')->report(...$filters);
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
            $this->response->setStatusCode(422);
        }
        return view('MCMS\\Modules\\Analytics\\Views\\index', [
            'title' => lang('Analytics.title'), 'adminMenu' => $this->adminMenu(), 'user' => auth()->user(),
            'report' => $report, 'filters' => $filters, 'error' => $error,
        ]);
    }
}
