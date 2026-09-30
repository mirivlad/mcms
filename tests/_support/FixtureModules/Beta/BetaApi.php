<?php

declare(strict_types=1);

namespace Tests\Support\FixtureModules\Beta;

final class BetaApi
{
    public function __construct(private readonly string $dependencyName)
    {
    }

    public function dependencyName(): string
    {
        return $this->dependencyName;
    }
}
