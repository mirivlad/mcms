<?php

declare(strict_types=1);

namespace MCMS\Modules\Media\Api;

interface MediaUsageInterface
{
    public function find(int $mediaId): array;
}
