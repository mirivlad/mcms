<?php

declare(strict_types=1);

namespace MCMS\Modules\Galleries\Api;

use MCMS\Modules\Media\Api\MediaUsageProviderInterface;

final class MediaUsageProvider implements MediaUsageProviderInterface
{
    public function findMediaUsage(int $mediaId, string $mediaUrl): array
    {
        $db = db_connect();
        if (! $db->tableExists('galleries') || ! $db->tableExists('gallery_images')) {
            return [];
        }
        $rows = $db->table('galleries g')->select('g.id, g.title')->distinct()
            ->join('gallery_images i', 'i.gallery_id = g.id', 'left')
            ->groupStart()->where('i.media_id', $mediaId)->orWhere('g.cover_media_id', $mediaId)->groupEnd()
            ->orderBy('g.id')->get()->getResultArray();
        return array_map(static fn (array $row): array => [
            'resource_id' => (int) $row['id'], 'label' => $row['title'], 'edit_url' => '/admin/galleries/' . $row['id'] . '/edit',
        ], $rows);
    }
}
