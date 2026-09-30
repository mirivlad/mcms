<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class Health extends BaseController
{
    public function index(): ResponseInterface
    {
        try {
            db_connect()->query('SELECT 1');
            return $this->response->setJSON([
                'status' => 'ok',
                'database' => 'ok',
            ]);
        } catch (Throwable $e) {
            return $this->response
                ->setStatusCode(503)
                ->setJSON([
                    'status' => 'error',
                    'database' => 'unavailable',
                ]);
        }
    }
}
