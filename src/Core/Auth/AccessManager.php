<?php

declare(strict_types=1);

namespace MCMS\Core\Auth;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use InvalidArgumentException;
use RuntimeException;

final class AccessManager
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function groups(): array
    {
        return (array) service('settings')->get('AuthGroups.groups');
    }

    public function permissions(): array
    {
        return (array) service('settings')->get('AuthGroups.permissions');
    }

    public function matrix(): array
    {
        return (array) service('settings')->get('AuthGroups.matrix');
    }

    public function saveGroup(string $id, string $title, string $description, array $permissions): void
    {
        $id = trim($id);
        $title = trim($title);
        $description = trim($description);

        if (! preg_match('/^[a-z][a-z0-9_-]*$/', $id)) {
            throw new InvalidArgumentException(
                'Group ID must use lowercase ASCII letters, numbers, dash or underscore.'
            );
        }

        if ($title === '') {
            throw new InvalidArgumentException('Group title is required.');
        }

        $availablePermissions = $this->permissions();
        $permissions = array_values(array_unique(array_filter(
            $permissions,
            static fn ($permission): bool => is_string($permission) && $permission !== ''
        )));

        foreach ($permissions as $permission) {
            if (! array_key_exists($permission, $availablePermissions)) {
                throw new InvalidArgumentException("Unknown permission: {$permission}");
            }
        }

        $settings = service('settings');
        $groups = $this->groups();
        $matrix = $this->matrix();

        $groups[$id] = [
            'title' => $title,
            'description' => $description,
        ];
        $matrix[$id] = $permissions;

        $settings->set('AuthGroups.groups', $groups);
        $settings->set('AuthGroups.matrix', $matrix);
    }

    public function syncUserAccess(User $user, array $groups, array $permissions): void
    {
        $availableGroups = $this->groups();
        $availablePermissions = $this->permissions();

        $groups = array_values(array_unique(array_filter(
            $groups,
            static fn ($group): bool => is_string($group) && $group !== ''
        )));
        $permissions = array_values(array_unique(array_filter(
            $permissions,
            static fn ($permission): bool => is_string($permission) && $permission !== ''
        )));

        foreach ($groups as $group) {
            if (! array_key_exists($group, $availableGroups)) {
                throw new InvalidArgumentException("Unknown group: {$group}");
            }
        }

        foreach ($permissions as $permission) {
            if (! array_key_exists($permission, $availablePermissions)) {
                throw new InvalidArgumentException("Unknown permission: {$permission}");
            }
        }

        if ($user->inGroup('superadmin')
            && ! in_array('superadmin', $groups, true)
            && $this->countSuperadmins() <= 1
        ) {
            throw new RuntimeException('The last super administrator cannot lose the superadmin group.');
        }

        $user->syncGroups(...$groups);
        $user->syncPermissions(...$permissions);
    }

    public function countSuperadmins(): int
    {
        if (! $this->db->tableExists('auth_groups_users')) {
            return 0;
        }

        return $this->db->table('auth_groups_users')
            ->where('group', 'superadmin')
            ->countAllResults();
    }
}
