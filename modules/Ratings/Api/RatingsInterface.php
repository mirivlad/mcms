<?php

declare(strict_types=1);

namespace MCMS\Modules\Ratings\Api;

interface RatingsInterface
{
    public function vote(string $targetType, string $targetId, ?int $userId, string $sessionId, int $stars): void;

    public function summary(string $targetType, string $targetId): array;

    public function userRating(string $targetType, string $targetId, ?int $userId, string $sessionId): ?int;

    public function list(int $limit = 50, int $offset = 0): array;

    public function delete(int $id): void;
}
