<?php

declare(strict_types=1);

namespace MCMS\Core\Events;

/** Optional live eligibility check for delayed notifications from a module. */
interface NotificationEventSourceInterface
{
    public function supportsNotification(string $event): bool;
    public function notificationCurrent(string $key): bool;
}
