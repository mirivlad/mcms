<?php

declare(strict_types=1);

namespace MCMS\Core\Content;

use MCMS\Core\Modules\ModuleServiceRegistry;

final class PublicSearch
{
    public function __construct(private readonly ModuleServiceRegistry $services)
    {
    }

    public function results(string $query, int $page = 1): array
    {
        $query = mb_substr(trim($query), 0, 120);
        $providers = [];
        $count = 0;
        if ($query !== '') {
            foreach ($this->services->all() as $provider) {
                if ($provider instanceof PublicSearchProviderInterface) {
                    $size = $provider->countSearch($query);
                    $providers[] = [$provider, $size];
                    $count += $size;
                }
            }
        }
        $lastPage = max(1, (int) ceil($count / 20));
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * 20;
        $items = [];
        // Stable module groups; each module supplies its own publication rules and order.
        foreach ($providers as [$provider, $size]) {
            if ($offset >= $size) {
                $offset -= $size;
                continue;
            }
            $items = [...$items, ...$provider->search($query, 20 - count($items), $offset)];
            $offset = 0;
            if (count($items) >= 20) {
                break;
            }
        }
        return ['query' => $query, 'items' => $items, 'count' => $count, 'page' => $page, 'lastPage' => $lastPage];
    }

    public static function excerpt(string $html): string
    {
        $text = ShortcodeRenderer::stripTokens(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return mb_substr(trim(preg_replace('/\s+/u', ' ', $text) ?? ''), 0, 220);
    }
}
