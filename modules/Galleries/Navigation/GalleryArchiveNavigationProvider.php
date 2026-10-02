<?php

declare(strict_types=1);

namespace MCMS\Modules\Galleries\Navigation;

use MCMS\Core\Navigation\PublicResourceProviderInterface;

final class GalleryArchiveNavigationProvider implements PublicResourceProviderInterface
{
    public function id(): string
    {
        return 'galleries.archive';
    }

    public function label(): string
    {
        return lang('Galleries.Navigation.archive');
    }

    public function search(string $query = '', int $limit = 50): array
    {
        $label = lang('Galleries.Public.title');
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
            'label' => lang('Galleries.Public.title'),
            'url' => '/galleries',
        ];
    }
    public function resources(): iterable
    {
        return $this->search();
    }

    public function resolveUrl(string $url): ?array
    {
        return $url === '/galleries' ? $this->resolve('index') : null;
    }
}
