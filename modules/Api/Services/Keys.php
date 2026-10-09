<?php

declare(strict_types=1);

namespace MCMS\Modules\Api\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use MCMS\Modules\Api\Api\ApiException;

final class Keys
{
    public const SCOPES = [
        'stock.read' => 'stock.view', 'stock.write' => 'stock.manage',
        'orders.read' => 'orders.export', 'delivery.read' => 'delivery.view',
    ];

    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function all(): array
    {
        $rows = $this->db->table('api_keys')->select('id,name,user_id,scopes,created_at,expires_at,revoked_at,last_used_at')->orderBy('id', 'DESC')->get()->getResultArray();
        return array_map(function (array $row): array {
            $row['id'] = (int) $row['id'];
            $row['user_id'] = (int) $row['user_id'];
            $row['scopes'] = json_decode($row['scopes'], true, 16, JSON_THROW_ON_ERROR);
            return $row;
        }, $rows);
    }

    public function create(string $name, array $scopes, int $userId, string $expires = ''): array
    {
        $name = trim($name);
        if (! array_is_list($scopes) || count(array_filter($scopes, 'is_string')) !== count($scopes)) {
            throw new ApiException(422, 'invalid_request', lang('Api.invalidKey'));
        }
        $scopes = array_values(array_unique($scopes));
        if ($name === '' || mb_strlen($name) > 190 || $scopes === [] || array_diff($scopes, array_keys(self::SCOPES)) !== []) {
            throw new ApiException(422, 'invalid_request', lang('Api.invalidKey'));
        }
        $user = $this->owner($userId);
        if ($user === null || ! $user->can('api.manage')) {
            throw new ApiException(403, 'forbidden', lang('Api.permissionDenied'));
        }
        foreach ($scopes as $scope) {
            if (! $user->can(self::SCOPES[$scope])) {
                throw new ApiException(403, 'forbidden', lang('Api.scopeDenied'));
            }
        }
        $expiry = null;
        if ($expires !== '') {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $expires);
            if ($date === false || $date->format('Y-m-d') !== $expires || $expires < date('Y-m-d')) {
                throw new ApiException(422, 'invalid_request', lang('Api.invalidExpiry'));
            }
            $expiry = $expires . ' 23:59:59';
        }
        $token = 'mcms_' . bin2hex(random_bytes(32));
        $this->db->table('api_keys')->insert([
            'name' => $name, 'user_id' => $userId, 'token_hash' => hash('sha256', $token),
            'scopes' => json_encode($scopes, JSON_THROW_ON_ERROR), 'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => $expiry, 'revoked_at' => null, 'last_used_at' => null,
        ]);
        return ['id' => (int) $this->db->insertID(), 'token' => $token];
    }

    public function revoke(int $id): void
    {
        $this->db->table('api_keys')->where('id', $id)->where('revoked_at', null)->update(['revoked_at' => date('Y-m-d H:i:s')]);
    }

    public function authenticate(string $authorization, string $scope): array
    {
        if (preg_match('/^Bearer (mcms_[a-f0-9]{64})$/iD', $authorization, $match) !== 1) {
            throw new ApiException(401, 'unauthorized', 'A valid Bearer API key is required.');
        }
        $row = $this->db->table('api_keys')->where('token_hash', hash('sha256', $match[1]))->get()->getRowArray();
        if ($row === null) {
            throw new ApiException(401, 'unauthorized', 'A valid Bearer API key is required.');
        }
        $this->authorize($row, $scope);
        $this->db->table('api_keys')->where('id', $row['id'])->update(['last_used_at' => date('Y-m-d H:i:s')]);
        $row['id'] = (int) $row['id'];
        $row['user_id'] = (int) $row['user_id'];
        unset($row['token_hash']);
        return $row;
    }

    /** Recheck revocation after the exchange transaction obtains the key lock. */
    public function requireActive(int $id, string $scope): void
    {
        $row = $this->db->table('api_keys')->where('id', $id)->get()->getRowArray();
        if ($row === null) {
            throw new ApiException(401, 'unauthorized', 'API key is no longer active.');
        }
        $this->authorize($row, $scope);
    }

    private function authorize(array $row, string $scope): void
    {
        $user = $this->owner((int) $row['user_id']);
        if ($row['revoked_at'] !== null || ($row['expires_at'] !== null && $row['expires_at'] <= date('Y-m-d H:i:s')) || $user === null) {
            throw new ApiException(401, 'unauthorized', 'API key is no longer active.');
        }
        $scopes = json_decode($row['scopes'], true, 16, JSON_THROW_ON_ERROR);
        if (! isset(self::SCOPES[$scope]) || ! in_array($scope, $scopes, true) || ! $user->can(self::SCOPES[$scope])) {
            throw new ApiException(403, 'forbidden', 'API key does not permit this operation.');
        }
    }

    private function owner(int $id): ?User
    {
        $user = model(UserModel::class)->findById($id);
        return $user !== null && $user->active && ! $user->isBanned() ? $user : null;
    }
}
