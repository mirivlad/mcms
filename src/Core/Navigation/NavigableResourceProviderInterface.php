<?php

declare(strict_types=1);

namespace MCMS\Core\Navigation;

interface NavigableResourceProviderInterface
{
    public function id(): string;

    public function label(): string;

    /**
     * @return list<array{id:string,label:string,url:string}>
     */
    public function search(string $query = '', int $limit = 50): array;

    /**
     * @return array{id:string,label:string,url:string}|null
     */
    public function resolve(string $resourceId): ?array;
}
