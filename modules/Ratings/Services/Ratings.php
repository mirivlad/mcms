<?php

declare(strict_types=1);

namespace MCMS\Modules\Ratings\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;
use InvalidArgumentException;
use MCMS\Core\Navigation\NavigableResourceRegistry;
use MCMS\Modules\Ratings\Api\RatingsInterface;

final class Ratings implements RatingsInterface
{
    public function __construct(
        private readonly BaseConnection $db,
        private readonly NavigableResourceRegistry $navigation,
        private readonly ?\MCMS\Core\Content\ResourcePolicy $policy = null,
    ) {
    }

    public function publicTarget(string $type, string $id): ?array
    {
        $enabled = array_map('trim', explode(',', (string) (setting('ratings.enabled_resources') ?? '')));
        $policy = $this->policy ?? new \MCMS\Core\Content\ResourcePolicy($this->db, 'ratings', 'Ratings.Admin.title', 'ratings_resource_options');
        if (! $policy->allowed($type, $id, in_array($type, $enabled, true))
            || preg_match('/^[a-z][a-z0-9._-]{0,31}$/D', $type) !== 1
            || preg_match('/^[a-zA-Z0-9._:-]{1,128}$/D', $id) !== 1) {
            return null;
        }
        return $this->navigation->resolve($type, $id);
    }

    public function vote(string $targetType, string $targetId, ?int $userId, string $sessionId, int $stars): void
    {
        if ($stars < 1 || $stars > 5 || $this->publicTarget($targetType, $targetId) === null
            || ($userId === null && ($sessionId === '' || ! (bool) setting('ratings.allow_guests')))) {
            throw new InvalidArgumentException(lang('Ratings.Errors.invalid'));
        }
        $voterHash = self::voterHash($userId, $sessionId);
        $query = $this->db->table('rating_votes')
            ->where('target_type', $targetType)->where('target_id', $targetId)
            ->where('voter_hash', $voterHash);
        $existing = $query->get()->getRowArray();
        $now = date('Y-m-d H:i:s');
        if ($existing !== null) {
            $this->db->table('rating_votes')->where('id', $existing['id'])
                ->update(['stars' => $stars, 'updated_at' => $now]);
            return;
        }
        try {
            $this->db->table('rating_votes')->insert([
                'target_type' => $targetType, 'target_id' => $targetId,
                'voter_hash' => $voterHash, 'user_id' => $userId,
                'stars' => $stars, 'created_at' => $now, 'updated_at' => $now,
            ]);
        } catch (DatabaseException) {
            // Another request may have inserted this identical identity first.
            $this->db->table('rating_votes')
                ->where('target_type', $targetType)->where('target_id', $targetId)
                ->where('voter_hash', $voterHash)
                ->update(['stars' => $stars, 'updated_at' => $now]);
        }
    }

    public function summary(string $targetType, string $targetId): array
    {
        $result = $this->db->table('rating_votes')
            ->select('stars, COUNT(*) AS votes', false)
            ->where('target_type', $targetType)->where('target_id', $targetId)
            ->groupBy('stars')->get()->getResultArray();
        $counts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($result as $row) {
            $rating = (int) $row['stars'];
            if (isset($counts[$rating])) {
                $counts[$rating] = (int) $row['votes'];
            }
        }
        $total = array_sum($counts);
        $sum = 0;
        foreach ($counts as $stars => $votes) {
            $sum += $stars * $votes;
        }
        return ['average' => $total > 0 ? round($sum / $total, 1) : null,
            'count' => $total, 'distribution' => $counts];
    }

    public function userRating(string $targetType, string $targetId, ?int $userId, string $sessionId): ?int
    {
        if ($userId === null && $sessionId === '') {
            return null;
        }
        $row = $this->db->table('rating_votes')->select('stars')
            ->where('target_type', $targetType)->where('target_id', $targetId)
            ->where('voter_hash', self::voterHash($userId, $sessionId))
            ->get()->getRowArray();
        return $row === null ? null : (int) $row['stars'];
    }

    public function list(int $limit = 50, int $offset = 0): array
    {
        return $this->db->table('rating_votes')->orderBy('id', 'DESC')
            ->limit(max(1, min(100, $limit)), max(0, $offset))->get()->getResultArray();
    }

    public function count(): int
    {
        return $this->db->table('rating_votes')->countAllResults();
    }

    public function delete(int $id): void
    {
        $this->db->table('rating_votes')->where('id', $id)->delete();
    }

    private static function voterHash(?int $userId, string $sessionId): string
    {
        return hash('sha256', $userId !== null ? 'user:' . $userId : 'guest:' . $sessionId);
    }
}
