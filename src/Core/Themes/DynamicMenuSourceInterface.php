<?php

declare(strict_types=1);

namespace MCMS\Core\Themes;

/**
 * Optional, read-only navigation source supplied by a module.
 * Unlike Menus records, its tree is generated from current domain data.
 */
interface DynamicMenuSourceInterface
{
    public function id(): string;

    public function label(): string;

    /**
     * @return list<array{label:string,url:string,children:array}>
     */
    public function tree(): array;
}
