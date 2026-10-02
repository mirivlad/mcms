<?php

declare(strict_types=1);

namespace MCMS\Core\Themes;

interface PageMetadataProviderInterface
{
    /** Return presentation metadata for a logical template; no content mutations. */
    public function metadata(string $template, array $data): array;
}
