<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\PublicMedia;
use PHPUnit\Framework\TestCase;

final class PublicMediaPathTest extends TestCase
{
    public function testOnlyCanonicalUploadsAndThumbnailsAreServed(): void
    {
        $id = str_repeat('a1', 16);
        foreach ([
            "2026/10/$id.jpg", "2026/10/$id.webp",
            "2026/10/$id.jpg.thumb.png", "2026/01/$id.pdf",
        ] as $path) {
            self::assertTrue(PublicMedia::validPath($path), $path);
        }
        foreach ([
            "../.env", "2026/10/../../.env", "2026/10/$id.php",
            "2026/10/$id.jpg.php", "2026/10/$id.svg",
            "2026/00/$id.png", "2026/13/$id.png",
            "2026/10/$id.jpg%00.php", "2026/10/.$id.jpg",
            "2026/10/$id.pdf.thumb.png/../../.env",
            "2026/10/$id.png/thumb.png", "2026/10/$id.png?x=1",
        ] as $path) {
            self::assertFalse(PublicMedia::validPath($path), $path);
        }
    }
}
