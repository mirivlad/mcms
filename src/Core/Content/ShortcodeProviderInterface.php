<?php

declare(strict_types=1);

namespace MCMS\Core\Content;

interface ShortcodeProviderInterface
{
    public function tag(): string;

    /** Returns trusted, escaped HTML for public content, or an empty string. */
    public function render(array $attributes): string;
}
