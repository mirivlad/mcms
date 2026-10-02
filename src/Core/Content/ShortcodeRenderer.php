<?php

declare(strict_types=1);

namespace MCMS\Core\Content;

use DOMDocument;
use DOMElement;
use DOMXPath;
use MCMS\Core\Modules\ModuleServiceRegistry;
use RuntimeException;

final class ShortcodeRenderer
{
    private const ATTRIBUTE = '[a-z][a-z0-9_-]*=(?:"[^"\[\]]*"|\'[^\'\[\]]*\'|[a-zA-Z0-9_-]+)';
    private const PATTERN = '~\[([a-z][a-z0-9_-]*)\s+(' . self::ATTRIBUTE . '(?:\s+' . self::ATTRIBUTE . ')*)\s*\]~';

    public function __construct(private readonly ModuleServiceRegistry $services)
    {
    }

    /** Expand text-node shortcodes in already sanitized content; never attributes or code examples. */
    public function render(string $html): string
    {
        if (! preg_match(self::PATTERN, $html)) {
            return $html;
        }
        $providers = [];
        foreach ($this->services->all() as $service) {
            if ($service instanceof ShortcodeProviderInterface) {
                $tag = $service->tag();
                if (isset($providers[$tag])) {
                    throw new RuntimeException('Duplicate shortcode provider: ' . $tag);
                }
                $providers[$tag] = $service;
            }
        }
        $doc = $this->document($html);
        $xpath = new DOMXPath($doc);
        $root = $xpath->query('//*[@data-mcms-shortcode-root="1"]')->item(0);
        if (! $root instanceof DOMElement) {
            return $html;
        }
        $nodes = iterator_to_array($xpath->query('.//text()[not(ancestor::pre or ancestor::code or ancestor::a or ancestor::script or ancestor::style or ancestor::textarea)]', $root));
        $cache = [];
        $count = 0;
        foreach ($nodes as $node) {
            $parts = preg_split(self::PATTERN, $node->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE);
            if ($parts === false || count($parts) === 1) {
                continue;
            }
            $fragment = $doc->createDocumentFragment();
            $fragment->appendChild($doc->createTextNode($parts[0]));
            for ($i = 1; $i < count($parts); $i += 3) {
                $tag = $parts[$i];
                $attributes = $this->attributes($parts[$i + 1]);
                // Structured but unsupported/disabled shortcodes disappear without changing stored content.
                if (++$count <= 50 && $attributes !== null && isset($providers[$tag])) {
                    $key = $tag . ':' . $parts[$i + 1];
                    $rendered = $cache[$key] ??= $providers[$tag]->render($attributes);
                    $embedded = $this->document($rendered);
                    $embeddedRoot = (new DOMXPath($embedded))->query('//*[@data-mcms-shortcode-root="1"]')->item(0);
                    if ($embeddedRoot !== null) {
                        foreach ($embeddedRoot->childNodes as $child) {
                            $fragment->appendChild($doc->importNode($child, true));
                        }
                    }
                }
                $fragment->appendChild($doc->createTextNode($parts[$i + 2]));
            }
            $node->parentNode->replaceChild($fragment, $node);
        }
        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $doc->saveHTML($child);
        }
        return $output;
    }

    private function attributes(string $input): ?array
    {
        preg_match_all('~([a-z][a-z0-9_-]*)=(?:"([^"\[\]]*)"|\'([^\'\[\]]*)\'|([a-zA-Z0-9_-]+))~', $input, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);
        $result = [];
        foreach ($matches as $match) {
            if (array_key_exists($match[1], $result)) {
                return null;
            }
            $result[$match[1]] = $match[2] ?? $match[3] ?? $match[4];
        }
        return $result;
    }

    private function document(string $html): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?><div data-mcms-shortcode-root="1">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $doc;
    }
}
