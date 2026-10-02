<?php

declare(strict_types=1);

namespace MCMS\Modules\Seo\Services;

use InvalidArgumentException;
use MCMS\Core\Themes\PageMetadataProviderInterface;

final class Metadata implements PageMetadataProviderInterface
{
    public const TYPES = ['page', 'news', 'home'];

    public function settings(): array
    {
        $values = [];
        foreach (self::TYPES as $type) {
            $values[$type] = [
                'title' => (string) (setting('seo.' . $type . '_title') ?? ($type === 'home' ? '{site_name}' : '{title} · {site_name}')),
                'description' => (string) (setting('seo.' . $type . '_description') ?? ''),
                'keywords' => (string) (setting('seo.' . $type . '_keywords') ?? ''),
                'keywords_source' => (string) (setting('seo.' . $type . '_keywords_source') ?? 'manual'),
            ];
        }
        return $values;
    }

    public function save(array $input): void
    {
        $settings = [];
        foreach (self::TYPES as $type) {
            $rule = (array) ($input[$type] ?? []);
            foreach (['title' => 255, 'description' => 320, 'keywords' => 1000] as $field => $limit) {
                $value = trim((string) ($rule[$field] ?? ''));
                if (mb_strlen($value) > $limit || ($field === 'title' && $value === '')) {
                    throw new InvalidArgumentException(lang('Seo.Errors.invalidRule'));
                }
                preg_match_all('/\{[^{}]*\}/u', $value, $tokens);
                if (array_diff($tokens[0], ['{title}', '{site_name}', '{tags}']) !== []) {
                    throw new InvalidArgumentException(lang('Seo.Errors.unknownToken'));
                }
                $settings['seo.' . $type . '_' . $field] = $value;
            }
            $source = (string) ($rule['keywords_source'] ?? 'manual');
            if (! in_array($source, ['manual', 'tags'], true)) {
                throw new InvalidArgumentException(lang('Seo.Errors.invalidSource'));
            }
            $settings['seo.' . $type . '_keywords_source'] = $source;
        }
        service('settings')->setMany($settings);
    }

    public function metadata(string $template, array $data): array
    {
        $type = match ($template) { 'page' => 'page', 'news.article' => 'news', 'home' => 'home', default => null };
        if ($type === null) return [];
        $content = (array) ($data['page'] ?? $data['article'] ?? []);
        if (! empty($data['isHome'])) $type = 'home';
        $rule = $this->settings()[$type];
        $tokens = ['{title}' => (string) ($content['title'] ?? $data['siteName'] ?? setting('Core.siteName') ?? 'mCMS'),
            '{site_name}' => (string) ($data['siteName'] ?? setting('Core.siteName') ?? 'mCMS'),
            '{tags}' => (string) ($content['tags'] ?? '')];
        $manualTitle = trim((string) ($content['seo_title'] ?? ''));
        $description = trim((string) ($content['seo_description'] ?? ''));
        $keywords = trim((string) ($content['seo_keywords'] ?? ''));
        return [
            'metaTitle' => $manualTitle !== '' ? $manualTitle : strtr($rule['title'], $tokens),
            'metaDescription' => $description !== '' ? $description : ($rule['description'] !== '' ? strtr($rule['description'], $tokens) : ($data['metaDescription'] ?? null)),
            'metaKeywords' => $keywords !== '' ? $keywords : ($rule['keywords_source'] === 'tags' ? $tokens['{tags}'] : strtr($rule['keywords'], $tokens)),
        ];
    }
}
