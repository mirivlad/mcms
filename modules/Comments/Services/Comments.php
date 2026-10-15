<?php

declare(strict_types=1);

namespace MCMS\Modules\Comments\Services;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use MCMS\Core\Navigation\NavigableResourceRegistry;
use MCMS\Modules\Comments\Api\CommentsInterface;

final class Comments implements CommentsInterface, \MCMS\Core\Events\NotificationEventSourceInterface
{
    private const STATUSES = ['pending', 'published', 'rejected', 'spam'];

    public function __construct(
        private readonly BaseConnection $db,
        private readonly NavigableResourceRegistry $navigation,
        private readonly ?\MCMS\Core\Content\ResourcePolicy $policy = null,
    ) {
    }

    public function permitted(string $targetType): bool
    {
        $types = explode(',', (string) (setting('comments.enabled_resources') ?? ''));
        return in_array($targetType, array_map('trim', $types), true);
    }

    public function publicTarget(string $targetType, string $targetId): ?array
    {
        $policy = $this->policy ?? new \MCMS\Core\Content\ResourcePolicy($this->db, 'comments', 'Comments.Admin.title', 'comments_resource_options');
        if (! $policy->allowed($targetType, $targetId, $this->permitted($targetType))
            || preg_match('/^[a-z][a-z0-9._-]{0,31}$/D', $targetType) !== 1
            || preg_match('/^[a-zA-Z0-9._:-]{1,128}$/D', $targetId) !== 1) {
            return null;
        }
        return $this->navigation->resolve($targetType, $targetId);
    }

    public function create(
        string $targetType,
        string $targetId,
        ?int $parentId,
        ?int $userId,
        string $authorName,
        string $authorEmail,
        string $body,
    ): array {
        if ($this->publicTarget($targetType, $targetId) === null) {
            throw new InvalidArgumentException(lang('Comments.Errors.notFound'));
        }
        $authorName = trim($authorName);
        $authorEmail = trim($authorEmail);
        $body = trim($body);
        if ($authorName === '' || mb_strlen($authorName) > 120 || $body === ''
            || mb_strlen($body) > 5000 || mb_strlen($body) < 2
            || strlen($authorEmail) > 254
            || ($authorEmail !== '' && filter_var($authorEmail, FILTER_VALIDATE_EMAIL) === false)) {
            throw new InvalidArgumentException(lang('Comments.Errors.invalid'));
        }
        if ($userId === null && ! (bool) setting('comments.allow_guests')) {
            throw new InvalidArgumentException(lang('Comments.Public.login'));
        }
        $depth = 0;
        if ($parentId !== null) {
            $parent = $this->db->table('comments')->where('id', $parentId)->get()->getRowArray();
            if ($parent === null || $parent['target_type'] !== $targetType
                || $parent['target_id'] !== $targetId || $parent['status'] !== 'published') {
                throw new InvalidArgumentException(lang('Comments.Errors.parent'));
            }
            $depth = (int) $parent['depth'] + 1;
            if ($depth > max(1, min(8, (int) setting('comments.max_depth')))) {
                throw new InvalidArgumentException(lang('Comments.Errors.parent'));
            }
        }
        $status = $userId !== null && (bool) setting('comments.auto_approve_users')
            ? 'published' : 'pending';
        $now = date('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            $this->db->table('comments')->insert([
                'target_type' => $targetType, 'target_id' => $targetId,
                'parent_id' => $parentId, 'depth' => $depth,
                'user_id' => $userId, 'author_name' => $authorName,
                'author_email' => $authorEmail === '' ? null : $authorEmail,
                'body' => $body, 'status' => $status, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $id = (int) $this->db->insertID();
            $this->recordNotification($id, $status);
            if (! $this->db->transStatus()) {
                throw new \RuntimeException('Cannot save comment.');
            }
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
        \CodeIgniter\Events\Events::trigger('mcms.comments.committed');
        return ['id' => $id, 'status' => $status, 'depth' => $depth];
    }

    public function published(string $targetType, string $targetId, int $limit = 100): array
    {
        $rows = $this->db->table('comments')
            ->select('id, parent_id, depth, user_id, author_name, body, status, created_at')
            ->where('target_type', $targetType)->where('target_id', $targetId)
            ->whereIn('status', ['published', 'deleted'])
            ->orderBy('id', 'ASC')->limit(max(1, min(200, $limit)))
            ->get()->getResultArray();
        $ids = array_fill_keys(array_column($rows, 'id'), true);
        $children = [];
        foreach ($rows as $row) {
            $parent = isset($ids[$row['parent_id'] ?? '']) ? (int) $row['parent_id'] : 0;
            $children[$parent][] = $row;
        }
        $ordered = [];
        $visit = static function (int $parent, int $depth) use (&$visit, &$ordered, $children): void {
            if ($depth > 8) {
                return;
            }
            foreach ($children[$parent] ?? [] as $row) {
                $row['display_depth'] = $depth;
                $ordered[] = $row;
                $visit((int) $row['id'], $depth + 1);
            }
        };
        $visit(0, 0);
        return $ordered;
    }

    public function list(string $status = 'pending', int $limit = 30, int $offset = 0): array
    {
        $builder = $this->db->table('comments');
        if (in_array($status, self::STATUSES, true)) {
            $builder->where('status', $status);
        }
        return $builder->orderBy('id', 'DESC')
            ->limit(max(1, min(100, $limit)), max(0, $offset))->get()->getResultArray();
    }

    public function count(string $status = 'pending'): int
    {
        $builder = $this->db->table('comments');
        if (in_array($status, self::STATUSES, true)) {
            $builder->where('status', $status);
        }
        return $builder->countAllResults();
    }

    public function setStatus(int $id, string $status): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Invalid comment status');
        }
        $this->db->transBegin();
        try {
            $current = $this->db->table('comments')->where('id', $id)->get()->getRowArray();
            if ($current !== null && $current['status'] !== 'deleted' && $current['status'] !== $status) {
                $this->db->table('comments')->where('id', $id)->where('status', $current['status'])
                    ->update(['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
                if ($this->db->affectedRows() === 1 && $status === 'published') {
                    $this->recordNotification($id, $status);
                }
            }
            if (! $this->db->transStatus()) {
                throw new \RuntimeException('Cannot moderate comment.');
            }
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
        \CodeIgniter\Events\Events::trigger('mcms.comments.committed');
    }

    public function supportsNotification(string $event): bool
    {
        return in_array($event, ['comments.pending', 'comments.published'], true);
    }

    public function notificationCurrent(string $key): bool
    {
        if (preg_match('/^comment:([1-9][0-9]*):(pending|published)$/D', $key, $match) !== 1) {
            return false;
        }
        $row = $this->db->table('comments')->where('id', (int) $match[1])->get()->getRowArray();
        return $row !== null && $row['status'] === $match[2]
            && $this->publicTarget($row['target_type'], $row['target_id']) !== null;
    }

    private function recordNotification(int $id, string $status): void
    {
        $row = $this->db->table('comments')->where('id', $id)->get()->getRowArray();
        $target = $this->publicTarget($row['target_type'], $row['target_id']);
        if ($target !== null && in_array($status, ['pending', 'published'], true)) {
            \CodeIgniter\Events\Events::trigger('mcms.comments.recorded', [
                'key' => 'comment:' . $id . ':' . $status, 'id' => $id, 'event' => $status,
                'author' => $row['author_name'], 'body' => $row['body'],
                'url' => $status === 'published' ? site_url($target['url']) . '#comment-' . $id : site_url('admin/comments'),
            ]);
        }
    }

    public function delete(int $id): void
    {
        // Tombstone preserves thread ancestry while removing personal data.
        $this->db->table('comments')->where('id', $id)->update([
            'author_name' => '[deleted]', 'author_email' => null, 'user_id' => null,
            'body' => '[deleted]', 'status' => 'deleted', 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('comments_reports')->where('comment_id', $id)->delete();
    }

    public function report(int $id, string $actor, string $reason): bool
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 250 || $actor === '') {
            throw new InvalidArgumentException(lang('Comments.Errors.invalid'));
        }
        $comment = $this->db->table('comments')->where('id', $id)
            ->where('status', 'published')->get()->getRowArray();
        if ($comment === null) {
            throw new InvalidArgumentException(lang('Comments.Errors.parent'));
        }
        $actorHash = hash('sha256', $actor);
        if ($this->db->table('comments_reports')->where('comment_id', $id)
            ->where('actor_hash', $actorHash)->countAllResults() > 0) {
            return false;
        }
        $this->db->table('comments_reports')->insert([
            'comment_id' => $id, 'actor_hash' => $actorHash,
            'reason' => $reason, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        return true;
    }

    public function reports(int $id): array
    {
        return $this->db->table('comments_reports')->where('comment_id', $id)
            ->orderBy('id', 'DESC')->get()->getResultArray();
    }
}
