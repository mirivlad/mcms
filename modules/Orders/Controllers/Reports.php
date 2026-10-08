<?php

declare(strict_types=1);

namespace MCMS\Modules\Orders\Controllers;

use App\Controllers\Admin\BaseAdminController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use MCMS\Modules\Orders\Api\ReportsInterface;
use RuntimeException;

final class Reports extends BaseAdminController
{
    public function index(): string|RedirectResponse
    {
        if (($redirect = $this->guard('orders.view')) !== null) {
            return $redirect;
        }
        $this->response->setHeader('Cache-Control', 'private, no-store');
        $filters = $this->filters();
        $error = null;
        $summary = [];
        try {
            $summary = $this->reports()->summary(...$filters);
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
            $this->response->setStatusCode(422);
        }

        return view('MCMS\\Modules\\Orders\\Views\\reports', [
            'title' => lang('Orders.Reports.title'), 'adminMenu' => $this->adminMenu(), 'user' => auth()->user(),
            'summary' => $summary, 'filters' => $filters, 'error' => $error,
            'statuses' => service('mcmsModuleServices')->get('orders.orders')->statuses(),
            'canExport' => $this->can('orders.export'),
        ]);
    }

    public function export(): ResponseInterface
    {
        if (! $this->can('orders.view') || ! $this->can('orders.export')) {
            return $this->response->setStatusCode(403)->setHeader('Cache-Control', 'private, no-store')
                ->setBody(lang('MCMS.errors.permissionDenied'));
        }
        $input = $this->request->getGet('format');
        $format = is_string($input) ? $input : '';
        $stream = tmpfile();
        if ($stream === false) {
            throw new RuntimeException(lang('Orders.Reports.exportFailed'));
        }
        try {
            $this->reports()->writeCsv($stream, $format, ...$this->filters());
            fflush($stream);
            $path = stream_get_meta_data($stream)['uri'];
            $response = $this->response->download($path, null)->setFileName('orders-' . $format . '-' . date('Y-m-d') . '.csv')
                ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->setHeader('Cache-Control', 'private, no-store')->setHeader('X-Content-Type-Options', 'nosniff');
            // Keep the private temporary file alive until DownloadResponse has sent it.
            register_shutdown_function(static function () use ($stream): void { fclose($stream); });

            return $response;
        } catch (InvalidArgumentException $e) {
            fclose($stream);
            return $this->response->setStatusCode(422)->setHeader('Cache-Control', 'private, no-store')->setBody($e->getMessage());
        } catch (\Throwable $e) {
            fclose($stream);
            throw $e;
        }
    }

    private function filters(): array
    {
        $value = function (string $name, string $default): string {
            $input = $this->request->getGet($name) ?? $default;
            return is_string($input) ? $input : 'invalid';
        };
        return [
            'from' => $value('from', date('Y-m-01')),
            'to' => $value('to', date('Y-m-d')),
            'status' => $value('status', ''),
        ];
    }

    private function reports(): ReportsInterface
    {
        return service('mcmsModuleServices')->get('orders.reports');
    }
}
