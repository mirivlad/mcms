<?php

declare(strict_types=1);

namespace MCMS\Modules\Orders\Api;

interface OrderFeedInterface
{
    /** ID-ordered saved orders; updatedSince is inclusive application-local SQL time. */
    public function exportPage(int $afterId = 0, int $limit = 100, ?int $untilId = null, string $updatedSince = '', string $status = ''): array;
}
