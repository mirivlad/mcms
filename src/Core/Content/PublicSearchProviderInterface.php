<?php

declare(strict_types=1);

namespace MCMS\Core\Content;

interface PublicSearchProviderInterface
{
    public function countSearch(string $query): int;

    /** @return array<array{title:string,url:string,text:string,type:string}> */
    public function search(string $query, int $limit, int $offset): array;
}
