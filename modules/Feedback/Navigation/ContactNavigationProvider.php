<?php

declare(strict_types=1);

namespace MCMS\Modules\Feedback\Navigation;

use MCMS\Core\Navigation\PublicResourceProviderInterface;

final class ContactNavigationProvider implements PublicResourceProviderInterface
{
    public function id(): string
    {
        return 'feedback.contact';
    }

    public function label(): string
    {
        return lang('Feedback.Public.title');
    }

    public function search(string $query = '', int $limit = 50): array
    {
        $label = lang('Feedback.Public.title');
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
            'label' => lang('Feedback.Public.title'),
            'url' => '/contact',
        ];
    }
    public function resources(): iterable
    {
        return $this->search();
    }

    public function resolveUrl(string $url): ?array
    {
        return $url === '/contact' ? $this->resolve('index') : null;
    }
}
