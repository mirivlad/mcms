<?php

declare(strict_types=1);

namespace MCMS\Modules\News\Navigation;

use MCMS\Core\Navigation\NavigableResourceProviderInterface;

final class NewsArchiveNavigationProvider implements NavigableResourceProviderInterface
{
    public function id(): string
    {
        return 'news.archive';
    }

    public function label(): string
    {
        return lang('News.Navigation.archive');
    }

    public function search(string $query = '', int $limit = 50): array
    {
        $label = lang('News.Public.title');
        $query = mb_strtolower(trim($query));

        if ($query !== '' && ! str_contains(mb_strtolower($label), $query)) {
            return [];
        }

        return [$this->resolve('index')];
    }

    public function resolve(string $resourceId): ?array
    {
        if ($resourceId !== 'index') {
            return null;
        }

        return [
            'id' => 'index',
            'label' => lang('News.Public.title'),
            'url' => '/news',
        ];
    }
}
