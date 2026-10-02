<?php

declare(strict_types=1);

namespace MCMS\Core\Navigation;

interface PublicResourceProviderInterface extends NavigableResourceProviderInterface
{
    /** Enumerates currently public resources without a search-result limit. */
    public function resources(): iterable;

    public function resolveUrl(string $url): ?array;
}
