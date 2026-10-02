<?php

declare(strict_types=1);

namespace MCMS\Core\Navigation;

interface PublicResourceProviderInterface extends NavigableResourceProviderInterface
{
    /**
     * Enumerates currently public resources without a search-result limit.
     * An optional updated_at is the last content change (Y-m-d H:i:s in the app timezone).
     * @return iterable<array{id:string,label:string,url:string,updated_at?:string}>
     */
    public function resources(): iterable;

    public function resolveUrl(string $url): ?array;
}
