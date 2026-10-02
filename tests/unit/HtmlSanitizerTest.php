<?php

declare(strict_types=1);

use MCMS\Core\Content\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HtmlSanitizerTest extends TestCase
{
    #[DataProvider('wrappedContent')]
    public function testSanitizesContentBeforeUnwrappingUnknownElements(string $input, string $expected): void
    {
        $sanitizer = new HtmlSanitizer();

        self::assertSame($expected, $sanitizer->sanitize($input));
        self::assertSame($expected, $sanitizer->sanitize($expected));
    }

    public static function wrappedContent(): array
    {
        return [
            'script' => ['<section><script>alert(1)</script></section>', ''],
            'image handler' => ['<section><img src="/ok.png" onerror="alert(1)"></section>', '<img src="/ok.png">'],
            'nested wrappers' => ['<section><article><a href="javascript:alert(1)">Link</a></article></section>', '<a>Link</a>'],
            'siblings' => ['<section><script>alert(1)</script><p onclick="alert(2)">Safe</p></section><p>Next</p>', '<p>Safe</p><p>Next</p>'],
            'safe formatting' => ['<section>Hello <strong>world</strong>.</section>', 'Hello <strong>world</strong>.'],
        ];
    }
}
