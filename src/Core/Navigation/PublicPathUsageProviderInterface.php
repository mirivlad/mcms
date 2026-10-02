<?php

declare(strict_types=1);

namespace MCMS\Core\Navigation;

interface PublicPathUsageProviderInterface
{
    /** Read-only conflicts with fixed public prefixes in stored content. */
    public function conflicts(array $paths): array;
}
