<?php

declare(strict_types=1);

namespace MCMS\Modules\News\Api;

use MCMS\Modules\Media\Api\HtmlMediaReferences;
use MCMS\Modules\Media\Api\MediaUsageProviderInterface;

final class MediaUsageProvider implements MediaUsageProviderInterface
{
    public function findMediaUsage(int $mediaId, string $mediaUrl): array
    {
        $db = db_connect();
        if (! $db->tableExists('news_articles')) {
            return [];
        }
        $usages = [];
        $cursor = 0;
        do {
            $rows = $db->table('news_articles')->where('id >', $cursor)->orderBy('id')->limit(100)->get()->getResultArray();
            foreach ($rows as $row) {
                $cursor = (int) $row['id'];
                if ((int) ($row['featured_media_id'] ?? 0) === $mediaId || HtmlMediaReferences::contains((string) $row['body'], $mediaUrl)) {
                    $usages[] = ['resource_id' => $cursor, 'label' => $row['title'], 'edit_url' => '/admin/news/' . $cursor . '/edit'];
                }
            }
        } while (count($rows) === 100);

        return $usages;
    }
}
