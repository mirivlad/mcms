<?php

declare(strict_types=1);

namespace MCMS\Modules\Media\Api;

use DOMDocument;
use DOMXPath;

final class HtmlMediaReferences
{
    public static function contains(string $html, string $mediaUrl): bool
    {
        if (trim($html) === '') {
            return false;
        }
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $target = rawurldecode((string) parse_url($mediaUrl, PHP_URL_PATH));
        $baseHost = parse_url(config('App')->baseURL, PHP_URL_HOST);
        foreach ((new DOMXPath($doc))->query('//@src | //@href') as $attribute) {
            $host = parse_url($attribute->value, PHP_URL_HOST);
            if ($host !== null && strcasecmp((string) $host, (string) $baseHost) !== 0) {
                continue;
            }
            $path = rawurldecode((string) parse_url($attribute->value, PHP_URL_PATH));
            if ($path === $target || $path === $target . '.thumb.png'
                || $path === rtrim((string) parse_url(config('App')->baseURL, PHP_URL_PATH), '/') . $target) {
                return true;
            }
        }

        return false;
    }
}
