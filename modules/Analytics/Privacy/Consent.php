<?php

declare(strict_types=1);

namespace MCMS\Modules\Analytics\Privacy;

/**
 * Explicit browser choice. DNT/GPC always take precedence over consent.
 */
final class Consent
{
    public const COOKIE = 'mcms_analytics_consent';

    public static function permitted(mixed $value, bool $dnt = false, bool $gpc = false): bool
    {
        return $value === 'yes' && ! $dnt && ! $gpc;
    }

    public static function undecided(mixed $value): bool
    {
        return $value !== 'yes' && $value !== 'no';
    }
}
