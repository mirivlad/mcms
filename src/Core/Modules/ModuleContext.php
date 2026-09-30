<?php

declare(strict_types=1);

namespace MCMS\Core\Modules;

use MCMS\Core\Admin\AdminRegistry;

final class ModuleContext
{
    public function __construct(
        public readonly ModuleManifest $manifest,
        public readonly AdminRegistry $admin,
    ) {
    }
}
