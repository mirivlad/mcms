<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

final class Core extends BaseConfig
{
    public string $siteName = 'mCMS';
    public bool $allowRegistration = true;
}
