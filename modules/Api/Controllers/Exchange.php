<?php

declare(strict_types=1);

namespace MCMS\Modules\Api\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use MCMS\Modules\Api\Api\ApiException;
use Throwable;

final class Exchange extends BaseController
{
    public function stock(): ResponseInterface
    {
        return $this->run('stock.read', fn () => $this->exchange()->stockPage(...$this->page()));
    }

    public function stockOne(string $id): ResponseInterface
    {
        return $this->run('stock.read', fn () => $this->exchange()->stockOne($this->id($id)));
    }

    public function updateStock(string $id): ResponseInterface
    {
        return $this->run('stock.write', function (array $key) use ($id): array {
            if (strtolower(trim(explode(';', $this->request->getHeaderLine('Content-Type'))[0])) !== 'application/json') {
                throw new ApiException(415, 'unsupported_media_type', 'Use Content-Type: application/json.');
            }
            $body = $this->request->getBody();
            if (strlen($body) > 16384) {
                throw new ApiException(413, 'request_too_large', 'Stock update body exceeds 16 KiB.');
            }
            try {
                $data = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new ApiException(400, 'invalid_json', 'Malformed JSON body.');
            }
            if (! is_array($data) || array_is_list($data)) {
                throw new ApiException(422, 'invalid_request', 'Stock update must be a JSON object.');
            }
            return $this->exchange()->updateStock($this->id($id), $data, $key, $this->request->getHeaderLine('Idempotency-Key'));
        });
    }

    public function orders(): ResponseInterface
    {
        return $this->run('orders.read', function (): array {
            $since = $this->request->getGet('updated_since') ?? '';
            $status = $this->request->getGet('status') ?? '';
            if (! is_string($since) || ! is_string($status)) {
                throw new ApiException(422, 'invalid_request', 'Invalid order filters.');
            }
            return $this->exchange()->ordersPage(...[...$this->page(), $since, $status]);
        });
    }

    public function order(string $id): ResponseInterface
    {
        return $this->run('orders.read', fn () => $this->exchange()->orderOne($this->id($id)));
    }

    public function delivery(): ResponseInterface
    {
        return $this->run('delivery.read', fn () => $this->exchange()->deliveryMethods());
    }

    private function run(string $scope, callable $operation): ResponseInterface
    {
        $serverTime = date('Y-m-d H:i:s');
        $this->response->setHeader('Cache-Control', 'private, no-store')->setHeader('X-Content-Type-Options', 'nosniff');
        try {
            $key = service('mcmsModuleServices')->get('api.keys')->authenticate($this->request->getHeaderLine('Authorization'), $scope);
            return $this->response->setJSON(['data' => $operation($key), 'meta' => [
                'api_version' => '1', 'server_time' => $serverTime, 'timezone' => config('App')->appTimezone,
            ]]);
        } catch (ApiException $e) {
            if ($e->status === 401) {
                $this->response->setHeader('WWW-Authenticate', 'Bearer realm="mCMS API"');
            }
            return $this->response->setStatusCode($e->status)->setJSON(['error' => ['code' => $e->errorCode, 'message' => $e->getMessage(), 'details' => $e->details]]);
        } catch (\InvalidArgumentException $e) {
            return $this->response->setStatusCode(422)->setJSON(['error' => ['code' => 'invalid_request', 'message' => $e->getMessage()]]);
        } catch (Throwable $e) {
            log_message('error', 'API exchange failed: {message}', ['message' => $e->getMessage()]);
            return $this->response->setStatusCode(500)->setJSON(['error' => ['code' => 'internal_error', 'message' => 'The exchange could not be completed.']]);
        }
    }

    private function page(): array
    {
        $after = $this->number($this->request->getGet('after_id') ?? '0', 0, PHP_INT_MAX);
        $limit = $this->number($this->request->getGet('limit') ?? '100', 1, 200);
        $until = $this->request->getGet('until_id');
        return [$after, $limit, $until === null ? null : $this->number($until, $after, PHP_INT_MAX)];
    }

    private function id(string $id): int
    {
        return $this->number($id, 1, PHP_INT_MAX);
    }

    private function number(mixed $value, int $min, int $max): int
    {
        if (! is_string($value) || preg_match('/^(0|[1-9][0-9]*)$/D', $value) !== 1
            || ($number = filter_var($value, FILTER_VALIDATE_INT)) === false || $number < $min || $number > $max) {
            throw new ApiException(422, 'invalid_request', 'Invalid integer parameter.');
        }
        return $number;
    }

    private function exchange(): \MCMS\Modules\Api\Services\Exchange
    {
        return service('mcmsModuleServices')->get('api.exchange');
    }
}
