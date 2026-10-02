<?php

declare(strict_types=1);

namespace MCMS\Modules\Feedback\Services;

use CodeIgniter\Database\BaseConnection;
use MCMS\Modules\Feedback\Api\FeedbackRepositoryInterface;
use InvalidArgumentException;

final class FeedbackRepository implements FeedbackRepositoryInterface
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function create(array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120 || strlen($email) > 254
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false || $message === '' || mb_strlen($message) > 5000) {
            throw new InvalidArgumentException(lang('Feedback.Errors.invalidMessage'));
        }
        $this->db->table('feedback_messages')->insert(['name' => $name, 'email' => $email, 'message' => $message, 'created_at' => date('Y-m-d H:i:s'), 'read_at' => null]);
        return (int) $this->db->insertID();
    }

    public function list(int $limit = 30, int $offset = 0): array
    {
        return $this->db->table('feedback_messages')->orderBy('id', 'DESC')->limit(max(1, min(100, $limit)), max(0, $offset))->get()->getResultArray();
    }

    public function count(): int
    {
        return $this->db->table('feedback_messages')->countAllResults();
    }

    public function find(int $id): ?array
    {
        return $this->db->table('feedback_messages')->where('id', $id)->get()->getRowArray();
    }

    public function markRead(int $id): void
    {
        $this->db->table('feedback_messages')->where('id', $id)->where('read_at', null)->update(['read_at' => date('Y-m-d H:i:s')]);
    }

    public function delete(int $id): void
    {
        $this->db->table('feedback_messages')->where('id', $id)->delete();
    }
}
