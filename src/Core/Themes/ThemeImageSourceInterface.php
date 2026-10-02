<?php

declare(strict_types=1);

namespace MCMS\Core\Themes;

interface ThemeImageSourceInterface
{
    /** @return array{id:int,title:string,url:string,thumbnail_url:string}|null */
    public function findImage(int $id): ?array;

    /** @return array{items:array,count:int} */
    public function images(string $query = '', int $offset = 0): array;
}
