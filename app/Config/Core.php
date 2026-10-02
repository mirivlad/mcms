<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

final class Core extends BaseConfig
{
    public string $siteName = 'mCMS';
    public string $locale = 'en';
    public bool $allowRegistration = true;
    public ?string $theme = null;

    /** @var array<string, array<string, int|null>> */
    public array $themeMenuAssignments = [];

    public array $themeAppearance = [];
}
