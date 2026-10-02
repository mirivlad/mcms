<?php

declare(strict_types=1);

namespace MCMS\Modules\Media\Services;

use MCMS\Core\Themes\ThemeImageSourceInterface;
use MCMS\Modules\Media\Api\MediaLibraryInterface;

final class ThemeImages implements ThemeImageSourceInterface
{
    public function __construct(private readonly MediaLibraryInterface $library)
    {
    }

    public function findImage(int $id): ?array
    {
        $item = $this->library->find($id);
        return $item === null || ! $item['is_image'] ? null : $this->image($item);
    }

    public function images(string $query = '', int $offset = 0): array
    {
        return [
            'items' => array_map($this->image(...), $this->library->list($query, 24, $offset, true)),
            'count' => $this->library->count($query, true),
        ];
    }

    private function image(array $item): array
    {
        return [
            'id' => $item['id'], 'title' => ($item['title'] ?? '') ?: $item['original_name'],
            'url' => $this->library->url($item), 'thumbnail_url' => $this->library->thumbnailUrl($item),
        ];
    }
}
