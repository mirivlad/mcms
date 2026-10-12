<?php

declare(strict_types=1);

namespace MCMS\Modules\Comments\Api;

interface CommentsInterface
{
    public function create(string $targetType, string $targetId, ?int $parentId, ?int $userId, string $authorName, string $authorEmail, string $body): array;

    public function published(string $targetType, string $targetId, int $limit = 100): array;

    public function list(string $status = 'pending', int $limit = 30, int $offset = 0): array;

    public function count(string $status = 'pending'): int;

    public function setStatus(int $id, string $status): void;

    public function delete(int $id): void;

    public function report(int $id, string $actor, string $reason): bool;
}
