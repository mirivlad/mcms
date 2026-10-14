<?php

declare(strict_types=1);

namespace MCMS\Core\Content;

/**
 * Presentation-only cleanup for author-entered content.
 * Stored HTML is not rewritten: editing still shows the original content.
 */
final class PublicContent
{
    public static function body(string $html, string $title): string
    {
        // A first H1 repeating the material title would duplicate the theme's H1.
        if (preg_match('~^\s*<h1\b[^>]*>(.*?)</h1\s*>\s*~isu', $html, $match) !== 1
            || self::normalize($match[1]) !== self::normalize($title)) {
            return $html;
        }

        return substr($html, strlen($match[0]));
    }

    public static function excerpt(string $excerpt, string $html, string $title = ''): string
    {
        // Suppress a summary when it is exactly the first paragraph of the body.
        if (preg_match('~^\s*<p\b[^>]*>(.*?)</p\s*>~isu', self::body($html, $title), $match) === 1
            && self::normalize($match[1]) === self::normalize($excerpt)) {
            return '';
        }

        return $excerpt;
    }

    private static function normalize(string $text): string
    {
        $plain = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $plain));
    }
}
