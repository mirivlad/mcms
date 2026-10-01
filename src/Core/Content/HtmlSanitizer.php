<?php

declare(strict_types=1);

namespace MCMS\Core\Content;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

final class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'blockquote', 'pre', 'code',
        'strong', 'b', 'em', 'i', 'u', 's',
        'a', 'img', 'figure', 'figcaption', 'hr',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'div', 'span',
    ];

    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'form',
        'input', 'button', 'textarea', 'select', 'option', 'svg', 'math',
    ];

    public function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        $doc->loadHTML(
            '<?xml encoding="utf-8" ?><div data-mcms-root="1">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($doc);
        $root = $xpath->query('//*[@data-mcms-root="1"]')->item(0);

        if (! $root instanceof DOMElement) {
            return '';
        }

        $this->sanitizeChildren($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $doc->saveHTML($child);
        }

        return trim($output);
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        for ($node = $parent->firstChild; $node !== null;) {
            $next = $node->nextSibling;

            if ($node instanceof DOMElement) {
                $tag = strtolower($node->tagName);

                if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                    $parent->removeChild($node);
                    $node = $next;
                    continue;
                }

                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    while ($node->firstChild !== null) {
                        $parent->insertBefore($node->firstChild, $node);
                    }
                    $parent->removeChild($node);
                    $node = $next;
                    continue;
                }

                $this->sanitizeAttributes($node, $tag);
                $this->sanitizeChildren($node);
            }

            $node = $next;
        }
    }

    private function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $allowed = match ($tag) {
            'a' => ['href', 'title', 'target', 'rel'],
            'img' => ['src', 'alt', 'title', 'width', 'height', 'class'],
            'th', 'td' => ['colspan', 'rowspan', 'class'],
            default => ['class'],
        };

        $names = [];
        foreach ($element->attributes as $attribute) {
            $names[] = $attribute->name;
        }

        foreach ($names as $name) {
            $lower = strtolower($name);

            if (! in_array($lower, $allowed, true)) {
                $element->removeAttribute($name);
                continue;
            }

            $value = trim($element->getAttribute($name));

            if ($lower === 'href' && ! $this->safeUrl($value, true)) {
                $element->removeAttribute($name);
            } elseif ($lower === 'src' && ! $this->safeUrl($value, false)) {
                $element->removeAttribute($name);
            } elseif ($lower === 'target' && ! in_array($value, ['_blank', '_self'], true)) {
                $element->removeAttribute($name);
            } elseif (in_array($lower, ['width', 'height', 'colspan', 'rowspan'], true)
                && ! preg_match('/^[1-9][0-9]{0,3}$/', $value)
            ) {
                $element->removeAttribute($name);
            } elseif ($lower === 'class') {
                $class = $this->safeClassList($value);
                if ($class === '') {
                    $element->removeAttribute($name);
                } else {
                    $element->setAttribute($name, $class);
                }
            }
        }

        if ($tag === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function safeUrl(string $value, bool $allowMailto): bool
    {
        if ($value === '' || str_starts_with($value, '/') || str_starts_with($value, '#')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return in_array($scheme, $allowMailto ? ['http', 'https', 'mailto'] : ['http', 'https'], true);
    }

    private function safeClassList(string $value): string
    {
        $classes = preg_split('/\s+/', trim($value)) ?: [];
        $classes = array_values(array_filter(
            $classes,
            static fn (string $class): bool => preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $class) === 1,
        ));

        return implode(' ', array_slice($classes, 0, 16));
    }
}
