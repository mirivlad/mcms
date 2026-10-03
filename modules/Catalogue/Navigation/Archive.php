<?php

declare(strict_types=1);

namespace MCMS\Modules\Catalogue\Navigation;

use MCMS\Core\Navigation\PublicResourceProviderInterface;

final class Archive implements PublicResourceProviderInterface
{
    public function id(): string
    {
        return 'catalogue.archive';
    }

    public function label(): string
    {
        return lang('Catalogue.navigationArchive');
    }

    public function search(string $query = '', int $limit = 50): array
    {
        $label = lang('Catalogue.name');
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
            'label' => lang('Catalogue.name'),
            'url' => '/shop',
        ];
    }
    public function resources(): iterable
    {
        return $this->search();
    }

    public function resolveUrl(string $url): ?array
    {
        return $url === '/shop' ? $this->resolve('index') : null;
    }
}
