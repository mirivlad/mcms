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
        if (! $this->validPath($source) || strlen($provider) > 32 || strlen($resourceId) > 190) {
            throw new InvalidArgumentException('Invalid resource redirect.');
        }
        $this->db->table('seo_redirects')->replace(['source_path' => $source, 'provider' => $provider, 'resource_id' => $resourceId, 'target_path' => null, 'created_at' => date('Y-m-d H:i:s')]);
    }

    public function find(string $source): ?array
    {
        return $this->db->table('seo_redirects')->where('source_path', $source)->get()->getRowArray();
    }

    public function save(string $source, string $target, ?string $original = null): void
    {
        $source = trim($source);
        $target = trim($target);
        if (! $this->validPath($source) || $source === '/' || ! $this->validPath($target)) {
            throw new InvalidArgumentException(lang('Seo.Errors.invalidPath'));
        }
        $first = explode('/', ltrim($source, '/'))[0];
        if (in_array($first, ['admin', 'assets', 'install', '_health', 'register', 'login', 'logout', 'auth', 'sitemap.xml'], true)
            || rtrim($source, '/') === '/search/results' || $this->resourceAt($source) !== null) {
            throw new InvalidArgumentException(lang('Seo.Errors.occupiedPath'));
        }
        if ($original !== null && $this->find($original) === null) {
            throw new InvalidArgumentException(lang('Seo.Errors.redirectNotFound'));
        }
        if ($source !== $original && $this->find($source) !== null) {
            throw new InvalidArgumentException(lang('Seo.Errors.duplicatePath'));
        }
        // Check the proposed edge against both manual and resource-backed records.
        $visited = [$source => true];
        $next = $target;
        while (true) {
            if (isset($visited[$next])) {
                throw new InvalidArgumentException(lang('Seo.Errors.redirectLoop'));
            }
            $visited[$next] = true;
            if ($next === '/' || $this->resourceAt($next) !== null || $next === $original) break;
            $row = $this->find($next);
            if ($row === null || ($next = $this->destination($row)) === null) break;
        }

        $resource = $this->resourceAt($target);
        $record = ['source_path' => $source, 'provider' => $resource['provider'] ?? '',
            'resource_id' => $resource['id'] ?? '', 'target_path' => $resource === null ? $target : null];
        if ($original === null) {
            $this->db->table('seo_redirects')->insert([...$record, 'created_at' => date('Y-m-d H:i:s')]);
        } else {
            $this->db->table('seo_redirects')->where('source_path', $original)->update($record);
        }
    }

    public function destination(array $row): ?string
    {
        if ($row['provider'] === '') return $row['target_path'] ?? null;
        return $this->navigation->resolve($row['provider'], $row['resource_id'])['url'] ?? null;
    }

    public function target(string $source): ?string
    {
        if ($this->find($source) === null) return null;
        $visited = [];
        $current = $source;
        while (true) {
            if (isset($visited[$current])) return null;
            $visited[$current] = true;
            // A published URL always wins, including at the end of a redirect chain.
            if ($current === '/' || $this->resourceAt($current) !== null) {
                return $current === $source ? null : $current;
            }
            $row = $this->find($current);
            if ($row === null) return $current === $source ? null : $current;
            $current = $this->destination($row);
            if ($current === null || ! $this->validPath($current)) return null;
        }
    }

    private function resourceAt(string $path): ?array
    {
        $path = rtrim($path, '/') ?: '/';
        foreach ($this->navigation->all() as $provider) {
            if ($provider instanceof PublicResourceProviderInterface && ($resource = $provider->resolveUrl($path)) !== null) {
                return [...$resource, 'provider' => $provider->id()];
            }
        }
        return null;
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
        return strlen($path) <= 500 && preg_match('~^/(?:[a-zA-Z0-9_.-]+(?:/[a-zA-Z0-9_.-]+)*/?)?$~', $path) === 1
            && ! array_intersect(explode('/', $path), ['.', '..']);
    }
}
