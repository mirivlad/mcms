<?php

declare(strict_types=1);

use MCMS\Modules\Analytics\Privacy\Consent;
use PHPUnit\Framework\TestCase;

final class AnalyticsConsentTest extends TestCase
{
    public function testTrackingRequiresExplicitOptIn(): void
    {
        self::assertFalse(Consent::permitted(null));
        self::assertFalse(Consent::permitted('no'));
        self::assertFalse(Consent::permitted('YES'));
        self::assertTrue(Consent::permitted('yes'));
        self::assertFalse(Consent::permitted('yes', true));
        self::assertFalse(Consent::permitted('yes', false, true));
    }

    public function testOnlyValidSavedChoicesSuppressNotice(): void
    {
        self::assertTrue(Consent::undecided(null));
        self::assertTrue(Consent::undecided('invalid'));
        self::assertFalse(Consent::undecided('yes'));
        self::assertFalse(Consent::undecided('no'));
    }
}
