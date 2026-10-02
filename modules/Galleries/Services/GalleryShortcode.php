<?php

declare(strict_types=1);

namespace MCMS\Modules\Galleries\Services;

use MCMS\Core\Content\ShortcodeProviderInterface;
use MCMS\Modules\Galleries\Api\GalleryRepositoryInterface;

final class GalleryShortcode implements ShortcodeProviderInterface
{
    public function __construct(private readonly GalleryRepositoryInterface $galleries)
    {
    }

    public function tag(): string
    {
        return 'gallery';
    }

    public function render(array $attributes): string
    {
        $display = $attributes['view'] ?? 'grid';
        if (array_diff(array_keys($attributes), ['id', 'view']) !== [] || ! isset($attributes['id'])
            || preg_match('/^[1-9][0-9]{0,18}$/', $attributes['id']) !== 1
            || ! in_array($display, ['grid', 'slider', 'carousel', 'cards'], true)) {
            return '';
        }
        $album = $this->galleries->find((int) $attributes['id']);
        if ($album === null || ! $album['is_live']) {
            return '';
        }
        if ($display === 'grid') {
            return service('mcmsThemes')->render('galleries.embed', ['album' => $album], 'MCMS\\Modules\\Galleries\\Views\\embed');
        }
        return service('mcmsThemes')->render('galleries.' . $display, ['album' => $album, 'display' => $display],
            'MCMS\\Modules\\Galleries\\Views\\' . ($display === 'slider' ? 'slider' : 'collection'));
    }
}
