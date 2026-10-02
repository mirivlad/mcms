<?php

declare(strict_types=1);

namespace MCMS\Modules\Media\Services;

use CodeIgniter\Database\BaseConnection;
use MCMS\Modules\Media\Api\MediaUsageInterface;
use MCMS\Modules\Media\Api\MediaUsageProviderInterface;
use RuntimeException;

final class MediaUsage implements MediaUsageInterface
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function find(int $mediaId): array
    {
        $media = $this->db->table('media_files')->where('id', $mediaId)->get()->getRowArray();
        if ($media === null) {
            return [];
        }
        $url = '/assets/uploads/media/' . implode('/', array_map('rawurlencode', explode('/', $media['storage_path'])));
        $usages = [];
        foreach (service('mcmsModules')->installedDataProviders('media.usage') as $owner => $class) {
            $provider = new $class();
            if (! $provider instanceof MediaUsageProviderInterface) {
                throw new RuntimeException("Invalid Media usage provider: {$owner}");
            }
            foreach ($provider->findMediaUsage($mediaId, $url) as $usage) {
                $usages[] = ['owner' => $owner, ...$usage];
            }
        }

        return $usages;
    }
}
