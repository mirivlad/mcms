<?php

declare(strict_types=1);

namespace MCMS\Modules\Pages\Api;

use MCMS\Modules\Media\Api\HtmlMediaReferences;
use MCMS\Modules\Media\Api\MediaUsageProviderInterface;

final class MediaUsageProvider implements MediaUsageProviderInterface
{
    public function findMediaUsage(int $mediaId, string $mediaUrl): array
    {
        $db = db_connect();
        if (! $db->tableExists('pages')) {
            return [];
        }
        $usages = [];
        $cursor = 0;
        do {
            $rows = $db->table('pages')->where('id >', $cursor)->orderBy('id')->limit(100)->get()->getResultArray();
            foreach ($rows as $row) {
                $cursor = (int) $row['id'];
                if (HtmlMediaReferences::contains((string) $row['body'], $mediaUrl)) {
                    $usages[] = ['resource_id' => $cursor, 'label' => $row['title'], 'edit_url' => '/admin/pages/' . $cursor . '/edit'];
                }
            }
        } while (count($rows) === 100);

        return $usages;
    }
}
