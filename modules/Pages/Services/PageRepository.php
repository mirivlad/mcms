<?php

declare(strict_types=1);

namespace MCMS\Modules\Pages\Services;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use MCMS\Modules\Pages\Api\PageRepositoryInterface;
use MCMS\Core\Content\HtmlSanitizer;
use MCMS\Core\Content\ContentWorkflow;
use RuntimeException;
use CodeIgniter\Events\Events;
use MCMS\Core\Events\CoreEvents;

final class PageRepository implements PageRepositoryInterface
{
    private const STATUSES = ['draft', 'published'];

    private const RESERVED_SLUGS = [
        'admin',
        'install',
        '_health',
        'register',
        'login',
        'logout',
        'auth',
        'theme-assets',
    ];

    public function __construct(
        private readonly BaseConnection $db,
        private readonly HtmlSanitizer $sanitizer,
        private readonly array $reservedPublicPaths = [],
        private readonly ?\MCMS\Core\Content\ResourceEditor $extensions = null,
    ) {
    }

    public function list(string $query = '', ?string $status = null, int $limit = 100, int $offset = 0): array
    {
        $builder = $this->db->table('pages');
        $query = trim($query);

        if ($query !== '') {
            $builder
                ->groupStart()
                ->like('title', $query)
                ->orLike('slug', $query)
                ->groupEnd();
        }

        if ($status !== null && in_array($status, self::STATUSES, true)) {
            $builder->where('status', $status);
        }

        $rows = $builder
            ->orderBy('updated_at', 'DESC')
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit(max(1, min(250, $limit)), max(0, $offset))
            ->get()
            ->getResultArray();

        return array_map(fn (array $row): array => $this->decorate($row), $rows);
    }

    public function count(string $query = '', ?string $status = null): int
    {
        $builder = $this->db->table('pages');
        $query = trim($query);

        if ($query !== '') {
            $builder
                ->groupStart()
                ->like('title', $query)
                ->orLike('slug', $query)
                ->groupEnd();
        }

        if ($status !== null && in_array($status, self::STATUSES, true)) {
            $builder->where('status', $status);
        }

        return $builder->countAllResults();
    }

    public function searchPublished(string $query, int $limit = 20, int $offset = 0): array
    {
        $rows = $this->searchBuilder($query)->orderBy('updated_at', 'DESC')->orderBy('id', 'DESC')
            ->limit(max(1, min(100, $limit)), max(0, $offset))->get()->getResultArray();
        return array_map(fn (array $row): array => $this->decorate($row), $rows);
    }

    public function countSearchPublished(string $query): int
    {
        return $this->searchBuilder($query)->countAllResults();
    }

    private function searchBuilder(string $query): object
    {
        $query = $this->db->escapeLikeString(trim($query));
        return $this->db->table('pages')->where('status', 'published')->groupStart()
            ->like('title', $query)->orLike('body', $query)->orLike('tags', $query)->groupEnd();
    }

    public function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = $this->db->table('pages')->where('id', $id)->get()->getRowArray();

        return $row === null ? null : $this->decorate($row);
    }

    public function findPublishedBySlug(string $slug): ?array
    {
        $slug = $this->normalizeSlug($slug, false);

        $row = $this->db->table('pages')
            ->where('slug', $slug)
            ->where('status', 'published')
            ->get()
            ->getRowArray();

        return $row === null ? null : $this->decorate($row);
    }

    public function preview(array $data, ?int $id = null): array
    {
        $current = $id === null ? null : $this->find($id);
        if ($id !== null && $current === null) {
            throw new RuntimeException(lang('Pages.Errors.notFound'));
        }
        return $this->decorate([
            ...$this->normalize($data, $current), 'id' => $id ?? 0,
            'created_by' => null, 'updated_by' => null,
            'created_at' => $current['created_at'] ?? date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function create(array $data, ?int $userId = null): array
    {
        $normalized = $this->normalize($data, null);
        $now = date('Y-m-d H:i:s');

        return $this->workflow()->transaction(function () use ($normalized, $now, $userId, $data): array {
            $this->db->table('pages')->insert([
                ...$normalized,
                'created_by' => $this->validUserId($userId),
                'updated_by' => $this->validUserId($userId),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $id = (int) $this->db->insertID();
            $page = $this->find($id);

            if ($page === null) {
                throw new RuntimeException(lang('Pages.Errors.notFound'));
            }

            ($this->extensions ?? service('mcmsResourceEditor'))->save('pages.page', (string) $page['id'], $data['resource_options'] ?? null);
            $this->workflow()->record((int) $page['id'], $page, $userId);
            return $page;
        });
    }

    public function update(int $id, array $data, ?int $userId = null): array
    {
        $current = $this->find($id);
        if ($current === null) {
            throw new RuntimeException(lang('Pages.Errors.notFound'));
        }

        $normalized = $this->normalize($data, $current);

        $page = $this->workflow()->transaction(function () use ($id, $normalized, $current, $userId, $data): array {
            if (! $this->workflow()->hasRevisions($id)) {
                $this->workflow()->record($id, $current, $current['updated_by']);
            }
            $this->db->table('pages')
                ->where('id', $id)
                ->update([
                    ...$normalized,
                    'updated_by' => $this->validUserId($userId),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            $page = $this->find($id);
            if ($page === null) {
                throw new RuntimeException(lang('Pages.Errors.notFound'));
            }
            ($this->extensions ?? service('mcmsResourceEditor'))->save('pages.page', (string) $id, $data['resource_options'] ?? null);
            $this->workflow()->record($id, $page, $userId);
            return $page;
        });

        if ($current['slug'] !== $page['slug']) {
            Events::trigger(CoreEvents::RESOURCE_MOVED, 'pages.page', (string) $id, $this->url($current), $this->url($page));
        }

        return $page;
    }

    public function delete(int $id): void
    {
        if ($id > 0) {
            $this->workflow()->transaction(function () use ($id): void {
                $this->workflow()->delete($id);
                $this->db->table('pages')->where('id', $id)->delete();
            });
        }
    }

    public function url(int|array $page): string
    {
        if (is_int($page)) {
            $page = $this->find($page);
            if ($page === null) {
                throw new RuntimeException(lang('Pages.Errors.notFound'));
            }
        }

        $slug = (string) ($page['slug'] ?? '');
        if ($slug === '') {
            throw new InvalidArgumentException(lang('Pages.Errors.invalidSlug'));
        }

        return '/' . rawurlencode($slug);
    }

    private function workflow(): ContentWorkflow
    {
        return new ContentWorkflow($this->db, 'pages');
    }

    private function normalize(array $data, ?array $currentPage): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException(lang('Pages.Errors.titleRequired'));
        }

        $title = mb_substr($title, 0, 255);

        $slugInput = trim((string) ($data['slug'] ?? ''));
        $slug = $slugInput === ''
            ? $this->slugify($title)
            : $this->normalizeSlug($slugInput, true);

        if ($slug === '') {
            throw new InvalidArgumentException(lang('Pages.Errors.invalidSlug'));
        }

        if (in_array($slug, [...self::RESERVED_SLUGS, ...$this->reservedPublicPaths], true)) {
            throw new InvalidArgumentException(lang('Pages.Errors.reservedSlug'));
        }

        $slug = $this->uniqueSlug($slug, $currentPage === null ? null : (int) $currentPage['id']);

        $status = trim((string) ($data['status'] ?? 'draft'));
        if (! in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException(lang('Pages.Errors.invalidStatus'));
        }

        $publishedAt = null;
        if ($status === 'published') {
            $publishedAt = $currentPage !== null
                && ($currentPage['status'] ?? null) === 'published'
                && ($currentPage['published_at'] ?? null) !== null
                    ? (string) $currentPage['published_at']
                    : date('Y-m-d H:i:s');
        }

        return [
            'title' => $title,
            'slug' => $slug,
            'body' => $this->sanitizer->sanitize((string) ($data['body'] ?? '')),
            'status' => $status,
            'seo_title' => $this->nullableText($data['seo_title'] ?? null, 255),
            'seo_description' => $this->nullableText($data['seo_description'] ?? null, 320),
            'seo_keywords' => $this->nullableText($data['seo_keywords'] ?? null, 1000),
            'tags' => \MCMS\Core\Content\TagNames::normalize((string) ($data['tags'] ?? '')),
            'published_at' => $publishedAt,
        ];
    }

    private function normalizeSlug(string $slug, bool $strict): string
    {
        $slug = strtolower(trim($slug));
        $slug = trim($slug, '-');

        if ($strict && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            throw new InvalidArgumentException(lang('Pages.Errors.invalidSlug'));
        }

        if (! $strict && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            return '';
        }

        return mb_substr($slug, 0, 190);
    }

    private function uniqueSlug(string $base, ?int $currentId): string
    {
        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($slug, $currentId)) {
            $ending = '-' . $suffix++;
            $slug = mb_substr($base, 0, 190 - strlen($ending)) . $ending;

            if ($suffix > 10000) {
                throw new RuntimeException(lang('Pages.Errors.duplicateSlug'));
            }
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $currentId): bool
    {
        $builder = $this->db->table('pages')->where('slug', $slug);

        if ($currentId !== null) {
            $builder->where('id !=', $currentId);
        }

        return $builder->countAllResults() > 0;
    }

    private function slugify(string $title): string
    {
        $map = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y',
            'к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f',
            'х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
        ];

        $value = mb_strtolower($title);
        $value = strtr($value, $map);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $this->normalizeSlug($value, false);
    }

    private function nullableText(mixed $value, int $limit): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }

    private function validUserId(?int $userId): ?int
    {
        return $userId !== null && $userId > 0 ? $userId : null;
    }

    private function decorate(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['created_by'] = $row['created_by'] === null ? null : (int) $row['created_by'];
        $row['updated_by'] = $row['updated_by'] === null ? null : (int) $row['updated_by'];
        $row['url'] = $this->url($row);

        return $row;
    }
}
