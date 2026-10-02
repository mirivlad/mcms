<?php

declare(strict_types=1);

namespace MCMS\Modules\Seo\Services;

use CodeIgniter\Database\BaseConnection;
use MCMS\Core\Navigation\NavigableResourceRegistry;
use MCMS\Core\Navigation\PublicResourceProviderInterface;
use InvalidArgumentException;

final class RedirectRepository
{
    public function __construct(private readonly BaseConnection $db, private readonly NavigableResourceRegistry $navigation)
    {
    }

    public function record(string $provider, string $resourceId, string $source): void
    {
        if (! $this->validPath($source) || strlen($source) > 500 || strlen($provider) > 32 || strlen($resourceId) > 190) {
            throw new InvalidArgumentException('Invalid resource redirect.');
        }
        $this->db->table('seo_redirects')->replace(['source_path' => $source, 'provider' => $provider, 'resource_id' => $resourceId, 'created_at' => date('Y-m-d H:i:s')]);
    }

    public function target(string $source): ?string
    {
        $row = $this->db->table('seo_redirects')->where('source_path', $source)->get()->getRowArray();
        if ($row === null) {
            return null;
        }
        foreach ($this->navigation->all() as $provider) {
            if ($provider instanceof PublicResourceProviderInterface && $provider->resolveUrl($source) !== null) {
                return null;
            }
        }
        $resource = $this->navigation->resolve($row['provider'], $row['resource_id']);
        $target = $resource['url'] ?? null;
        return is_string($target) && $target !== $source && $this->validPath($target) ? $target : null;
    }

    public function list(int $limit = 50, int $offset = 0): array
    {
        return $this->db->table('seo_redirects')->orderBy('created_at', 'DESC')->orderBy('source_path')->limit(max(1, min(100, $limit)), max(0, $offset))->get()->getResultArray();
    }

    public function count(): int
    {
        return $this->db->table('seo_redirects')->countAllResults();
    }

    public function delete(string $source): void
    {
        $this->db->table('seo_redirects')->where('source_path', $source)->delete();
    }

    private function validPath(string $path): bool
    {
        return preg_match('~^/[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*$~', $path) === 1;
    }
}
