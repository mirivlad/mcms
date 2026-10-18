<?php

declare(strict_types=1);

namespace MCMS\Core\Auth;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use InvalidArgumentException;
use RuntimeException;

/** Administrative mutations. Shield continues to own identities and soft deletes. */
final class UserAdministration
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function mayManage(User $target, User $actor): bool
    {
        return ! in_array('superadmin', $target->getGroups() ?? [], true)
            || in_array('superadmin', $actor->getGroups() ?? [], true);
    }

    public function updateProfile(User $target, string $username, string $email, string $name, string $phone): void
    {
        if (! $this->mayManage($target, $this->actor())) {
            throw new InvalidArgumentException(lang('MCMS.users.protected'));
        }
        if (preg_match('/\A[a-zA-Z0-9.]{3,30}\z/D', $username) !== 1
            || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254
            || mb_strlen($name) > 190 || mb_strlen($phone) > 64) {
            throw new InvalidArgumentException(lang('MCMS.users.invalidProfile'));
        }
        $provider = auth()->getProvider();
        $existing = $provider->findByCredentials(['email' => $email]);
        if ($existing instanceof User && (int) $existing->id !== (int) $target->id) {
            throw new InvalidArgumentException(lang('MCMS.errors.userExists'));
        }
        $this->db->transBegin();
        try {
            $target->username = $username;
            $target->setEmail($email);
            if (! $provider->save($target)) {
                throw new InvalidArgumentException(implode(' ', $provider->errors()));
            }
            $data = ['full_name' => $name, 'phone' => $phone, 'updated_at' => date('Y-m-d H:i:s')];
            if ($this->db->table('account_profiles')->where('user_id', $target->id)->countAllResults() > 0) {
                $this->db->table('account_profiles')->where('user_id', $target->id)->update($data);
            } else {
                $data['user_id'] = $target->id;
                $data['created_at'] = date('Y-m-d H:i:s');
                $this->db->table('account_profiles')->insert($data);
            }
            if ($this->db->transStatus() === false) throw new RuntimeException(lang('MCMS.account.saveFailed'));
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function setPassword(User $target, User $actor, string $password): void
    {
        $this->checkSensitive($target, $actor);
        $result = service('passwords')->check($password, $target);
        if (! $result->isOK()) throw new InvalidArgumentException($result->reason());
        if ($target->getPasswordHash() !== null && password_verify($password, $target->getPasswordHash())) {
            throw new InvalidArgumentException(lang('MCMS.users.passwordSame'));
        }
        $target->setPassword($password);
        $provider = auth()->getProvider();
        if (! $provider->save($target)) throw new RuntimeException(lang('MCMS.account.saveFailed'));
        // Invalidate any persistent "remember me" authentication for this account.
        auth('session')->getAuthenticator()->forget($target);
    }

    public function resetPassword(User $target, User $actor): string
    {
        // Securely generated initial credential shown only once to an authorized operator.
        $password = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $this->setPassword($target, $actor, $password);
        return $password;
    }

    /** @param array<mixed> $rawIds */
    public function bulk(array $rawIds, string $action, User $actor): void
    {
        if (! in_array($action, ['activate', 'deactivate', 'delete'], true)) {
            throw new InvalidArgumentException(lang('MCMS.users.invalidAction'));
        }
        if (count($rawIds) < 1 || count($rawIds) > 100) {
            throw new InvalidArgumentException(lang('MCMS.users.selectionRequired'));
        }
        $ids = [];
        foreach ($rawIds as $value) {
            if ((! is_string($value) && ! is_int($value)) || ! ctype_digit((string) $value)
                || (int) $value <= 0) throw new InvalidArgumentException(lang('MCMS.users.invalidAction'));
            $ids[] = (int) $value;
        }
        $ids = array_values(array_unique($ids));
        if (in_array((int) $actor->id, $ids, true)) {
            throw new InvalidArgumentException(lang('MCMS.users.selfProtected'));
        }

        $provider = auth()->getProvider();
        $this->db->transBegin();
        try {
            $targets = $provider->withGroups()->find($ids);
            if (count($targets) !== count($ids)) throw new InvalidArgumentException(lang('MCMS.errors.userNotFound'));
            foreach ($targets as $target) {
                $this->checkSensitive($target, $actor);
            }
            if ($action !== 'activate') {
                $allActiveAdmins = (int) $this->db->table('users')
                    ->join('auth_groups_users', 'auth_groups_users.user_id = users.id')
                    ->where('auth_groups_users.group', 'superadmin')
                    ->where('users.deleted_at', null)
                    ->groupStart()->where('users.status', null)->orWhere('users.status !=', 'banned')->groupEnd()
                    ->countAllResults();
                $removedAdmins = 0;
                foreach ($targets as $target) {
                    if (! $target->isBanned() && in_array('superadmin', $target->getGroups() ?? [], true)) $removedAdmins++;
                }
                if ($allActiveAdmins <= $removedAdmins) {
                    throw new InvalidArgumentException(lang('MCMS.users.lastAdmin'));
                }
            }
            foreach ($targets as $target) {
                if ($action === 'delete') {
                    // Shield soft delete preserves historical orders, comments and user relations.
                    if (! $provider->delete((int) $target->id)) throw new RuntimeException(lang('MCMS.account.saveFailed'));
                } else {
                    $action === 'activate' ? $target->unBan() : $target->ban(lang('MCMS.users.accountInactive'));
                }
                if ($action !== 'activate') auth('session')->getAuthenticator()->forget($target);
            }
            if ($this->db->transStatus() === false) throw new RuntimeException(lang('MCMS.account.saveFailed'));
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    private function checkSensitive(User $target, User $actor): void
    {
        if ((int) $target->id === (int) $actor->id) {
            throw new InvalidArgumentException(lang('MCMS.users.selfProtected'));
        }
        if (! $this->mayManage($target, $actor)) {
            throw new InvalidArgumentException(lang('MCMS.users.protected'));
        }
    }

    private function actor(): User
    {
        $actor = auth()->user();
        if (! $actor instanceof User) throw new RuntimeException(lang('MCMS.errors.permissionDenied'));
        return $actor;
    }
}
