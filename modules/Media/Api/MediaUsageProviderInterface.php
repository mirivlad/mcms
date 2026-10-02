<?php

declare(strict_types=1);

namespace MCMS\Modules\Media\Api;

interface MediaUsageProviderInterface
{
    /** Read-only references, including drafts and data of a disabled module. */
    public function findMediaUsage(int $mediaId, string $mediaUrl): array;
}
