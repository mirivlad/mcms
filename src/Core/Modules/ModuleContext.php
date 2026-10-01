<?php

declare(strict_types=1);

namespace MCMS\Core\Modules;

use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Navigation\NavigableResourceRegistry;

final class ModuleContext
{
    public function __construct(
        public readonly ModuleManifest $manifest,
        public readonly AdminRegistry $admin,
        public readonly ModuleServiceRegistry $services,
        public readonly NavigableResourceRegistry $navigation,
    ) {
    }
}
