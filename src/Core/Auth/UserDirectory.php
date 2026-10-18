<?php

declare(strict_types=1);

namespace MCMS\Core\Auth;

use CodeIgniter\Database\BaseConnection;

/**
 * Bounded directory lookup; Shield remains the owner of user entities and permissions.
 */
final class UserDirectory
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /** @return array{query:string,ids:list<int>,count:int,page:int,lastPage:int} */
    public function page(string $query, int $requestedPage = 1, int $perPage = 25, string $username = '', string $email = '', string $group = ''): array
    {
        $query = mb_substr(trim($query), 0, 120);
        $username = mb_substr(trim($username), 0, 120);
        $email = mb_substr(trim($email), 0, 120);
        $group = mb_substr(trim($group), 0, 80);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

        $builder = $this->db->table('users')
            ->select('users.id')
            ->join('auth_identities', "auth_identities.user_id = users.id AND auth_identities.type = 'email_password'", 'left')
            ->where('users.deleted_at', null)
            ->distinct();

        if ($query !== '') {
            // LIKE adds surrounding wildcards; escape only the visitor-supplied ones.
            $needle = strtr($query, ['!' => '!!', '%' => '!%', '_' => '!_']);
            $builder->groupStart()
                ->like('users.username', $needle)
                ->orLike('auth_identities.secret', $needle)
                ->groupEnd();
        }

        if ($username !== '') {
            $builder->like('users.username', strtr($username, ['!' => '!!', '%' => '!%', '_' => '!_']));
        }
        if ($email !== '') {
            $builder->like('auth_identities.secret', strtr($email, ['!' => '!!', '%' => '!%', '_' => '!_']));
        }
        if ($group !== '') {
            $builder->join('auth_groups_users', 'auth_groups_users.user_id = users.id')
                ->where('auth_groups_users.group', $group);
        }
        $count = (clone $builder)->countAllResults();
        $lastPage = max(1, (int) ceil($count / $perPage));
        $page = max(1, min($requestedPage, $lastPage));
        $records = $builder
            ->orderBy('users.id', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()->getResultArray();

        return [
            'query' => $query,
            'usernameFilter' => $username,
            'emailFilter' => $email,
            'groupFilter' => $group,
            'perPage' => $perPage,
            'ids' => array_map('intval', array_column($records, 'id')),
            'count' => $count,
            'page' => $page,
            'lastPage' => $lastPage,
        ];
    }
}
