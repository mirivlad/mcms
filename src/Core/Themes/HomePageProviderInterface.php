<?php

declare(strict_types=1);

namespace MCMS\Core\Themes;

interface HomePageProviderInterface
{
    /** Null leaves the built-in home page available. */
    public function renderHome(): ?string;
}
