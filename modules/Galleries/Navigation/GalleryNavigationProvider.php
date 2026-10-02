<?php

declare(strict_types=1);

namespace MCMS\Modules\Galleries\Navigation;

use MCMS\Core\Navigation\PublicResourceProviderInterface;
use MCMS\Modules\Galleries\Api\GalleryRepositoryInterface;

final class GalleryNavigationProvider implements PublicResourceProviderInterface
{
    public function __construct(private readonly GalleryRepositoryInterface $galleries)
    {
    }

    public function id(): string
    {
        return 'galleries.album';
    }

    public function label(): string
    {
        return lang('Galleries.Navigation.albums');
    }

    public function search(string $query = '', int $limit = 50): array
    {
        return array_map(
            fn (array $album): array => $this->resource($album),
            $this->galleries->listPublished($query, max(1, min(100, $limit))),
        );
    }

    public function resolve(string $resourceId): ?array
    {
        if (preg_match('/^[1-9][0-9]*$/', $resourceId) !== 1) {
            return null;
        }

        $album = $this->galleries->find((int) $resourceId);
        if ($album === null || ! (bool) ($album['is_live'] ?? false)) {
            return null;
        }

        return $this->resource($album);
    }

    public function resources(): iterable
    {
        $offset = 0;
        do {
            $rows = $this->galleries->listPublished('', 100, $offset);
            foreach ($rows as $row) {
                yield $this->resource($row);
            }
            $offset += 100;
        } while (count($rows) === 100);
    }

    public function resolveUrl(string $url): ?array
    {
        if (preg_match('~^/galleries/([a-z0-9]+(?:-[a-z0-9]+)*)$~', $url, $matches) !== 1) {
            return null;
        }
        $row = $this->galleries->findPublishedBySlug($matches[1]);
        return $row === null ? null : $this->resource($row);
    }

    private function resource(array $album): array
    {
        return [
            'id' => (string) $album['id'],
            'label' => (string) $album['title'],
            'url' => (string) $album['url'],
        ];
    }
}
