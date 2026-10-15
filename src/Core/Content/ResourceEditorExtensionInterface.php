<?php

declare(strict_types=1);

namespace MCMS\Core\Content;

/** Optional module-owned controls, saved within the resource owner's transaction. */
interface ResourceEditorExtensionInterface
{
    public function key(): string;
    public function label(): string;
    public function value(string $type, string $id): string;
    public function save(string $type, string $id, string $value): void;
}
