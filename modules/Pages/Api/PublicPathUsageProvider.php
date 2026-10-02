<?php

declare(strict_types=1);

namespace MCMS\Modules\Pages\Api;

use MCMS\Core\Navigation\PublicPathUsageProviderInterface;

final class PublicPathUsageProvider implements PublicPathUsageProviderInterface
{
    public function conflicts(array $paths): array
    {
        $db = db_connect();
        if ($paths === [] || ! $db->tableExists('pages')) {
            return [];
        }
        return array_map(static fn (array $row): string => '/' . $row['slug'],
            $db->table('pages')->select('slug')->whereIn('slug', $paths)->orderBy('slug')->get()->getResultArray());
    }
}
