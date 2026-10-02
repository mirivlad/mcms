<?php

declare(strict_types=1);

namespace MCMS\Core\Content;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/** Storage is owned by the calling module; drafts are private to their editor. */
final class ContentWorkflow
{
    public function __construct(
        private readonly BaseConnection $db,
        private readonly string $prefix,
    ) {
    }

    public function transaction(callable $action): mixed
    {
        $this->db->transBegin();
        try {
            $result = $action();
            if (! $this->db->transStatus()) {
                throw new RuntimeException(lang('MCMS.content.saveFailed'));
            }
            $this->db->transCommit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function record(int $id, array $snapshot, ?int $userId): void
    {
        unset($snapshot['featured_media']);
        $this->db->table($this->prefix . '_revisions')->insert([
            'content_id' => $id,
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function hasRevisions(int $id): bool
    {
        return $this->db->table($this->prefix . '_revisions')->where('content_id', $id)->countAllResults() > 0;
    }

    public function history(int $id, int $offset = 0): array
    {
        $builder = $this->db->table($this->prefix . '_revisions')->where('content_id', $id);
        $count = $builder->countAllResults(false);
        $rows = $builder->orderBy('id', 'DESC')->limit(20, max(0, $offset))->get()->getResultArray();
        foreach ($rows as &$row) {
            $snapshot = json_decode($row['snapshot'], true, 512, JSON_THROW_ON_ERROR);
            unset($row['snapshot']);
            $row['title'] = $snapshot['title'] ?? '';
        }
        return ['items' => $rows, 'count' => $count];
    }

    public function revision(int $id, int $revisionId): ?array
    {
        $row = $this->db->table($this->prefix . '_revisions')
            ->where('content_id', $id)->where('id', $revisionId)->get()->getRowArray();
        return $row === null ? null : json_decode($row['snapshot'], true, 512, JSON_THROW_ON_ERROR);
    }

    public function saveDraft(int $userId, int $id, array $snapshot): void
    {
        // Incomplete fields are intentional: autosave must also protect an untitled draft.
        $snapshot['body'] = (new HtmlSanitizer())->sanitize((string) ($snapshot['body'] ?? ''));
        $this->db->table($this->prefix . '_drafts')->upsert([
            'user_id' => $userId, 'content_id' => $id,
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function draft(int $userId, int $id): ?array
    {
        $row = $this->db->table($this->prefix . '_drafts')
            ->where('user_id', $userId)->where('content_id', $id)->get()->getRowArray();
        if ($row === null) {
            return null;
        }
        return ['data' => json_decode($row['snapshot'], true, 512, JSON_THROW_ON_ERROR), 'updated_at' => $row['updated_at']];
    }

    public function discardDraft(int $userId, int $id): void
    {
        $this->db->table($this->prefix . '_drafts')->where('user_id', $userId)->where('content_id', $id)->delete();
    }

    public function delete(int $id): void
    {
        $this->db->table($this->prefix . '_revisions')->where('content_id', $id)->delete();
        $this->db->table($this->prefix . '_drafts')->where('content_id', $id)->delete();
    }
}
