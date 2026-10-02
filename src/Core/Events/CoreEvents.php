<?php

declare(strict_types=1);

namespace MCMS\Core\Events;

final class CoreEvents
{
    public const USER_CREATED = 'mcms.user.created';
    public const MODULE_ENABLED = 'mcms.module.enabled';
    public const MODULE_DISABLED = 'mcms.module.disabled';
    public const SETTINGS_CHANGED = 'mcms.settings.changed';
    public const RESOURCE_MOVED = 'mcms.resource.moved';

    private function __construct()
    {
    }
}
