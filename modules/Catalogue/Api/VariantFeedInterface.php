<?php

declare(strict_types=1);

namespace MCMS\Modules\Catalogue\Api;

interface VariantFeedInterface
{
    /** ID-ordered inventory identities, including drafts. */
    public function variantPage(int $afterId = 0, int $limit = 100, ?int $untilId = null): array;
}
