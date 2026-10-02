<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use MCMS\Modules\Media\Api\HtmlMediaReferences;

final class HtmlMediaReferencesTest extends CIUnitTestCase
{
    public function testDetectsActualImageAndDownloadLinks(): void
    {
        $url = '/assets/uploads/media/2026/10/test.png';
        self::assertTrue(HtmlMediaReferences::contains('<img src="' . $url . '">', $url));
        self::assertTrue(HtmlMediaReferences::contains('<a href="http://example.com' . $url . '">File</a>', $url));
        self::assertTrue(HtmlMediaReferences::contains('<img src="' . $url . '.thumb.png">', $url));
        self::assertFalse(HtmlMediaReferences::contains('<p>' . $url . '</p>', $url));
        self::assertFalse(HtmlMediaReferences::contains('<img src="https://other.example' . $url . '">', $url));
    }
}
