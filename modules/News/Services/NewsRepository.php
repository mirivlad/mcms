<?php

declare(strict_types=1);

namespace MCMS\Modules\News\Services;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use MCMS\Core\Content\HtmlSanitizer;
use MCMS\Core\Content\ContentWorkflow;
use MCMS\Core\Content\ShortcodeRenderer;
use MCMS\Modules\Media\Api\MediaLibraryInterface;
use MCMS\Modules\News\Api\NewsRepositoryInterface;
use RuntimeException;
use CodeIgniter\Events\Events;
use MCMS\Core\Events\CoreEvents;

final class NewsRepository implements NewsRepositoryInterface
{
    private const STATUSES = ['draft', 'published'];

    public function __construct(
        private readonly BaseConnection $db,
        private readonly HtmlSanitizer $sanitizer,
        private readonly MediaLibraryInterface $media,
    ) {
    }

    public function list(string $query = '', ?string $status = null, int $limit = 100, int $offset = 0): array
    {
        $builder = $this->db->table('news_articles');
        $this->applySearch($builder, $query);

        if ($status !== null && in_array($status, self::STATUSES, true)) {
            $builder->where('status', $status);
        }

        $rows = $builder
            ->orderBy('published_at', 'DESC')
            ->orderBy('updated_at', 'DESC')
            ->limit(max(1, min(250, $limit)), max(0, $offset))
            ->get()
            ->getResultArray();

        return array_map(fn (array $row): array => $this->decorate($row), $rows);
    }

    public function count(string $query = '', ?string $status = null): int
    {
        $builder = $this->db->table('news_articles');
        $this->applySearch($builder, $query);

        if ($status !== null && in_array($status, self::STATUSES, true)) {
            $builder->where('status', $status);
        }

        return $builder->countAllResults();
    }

    public function listPublished(string $query = '', int $limit = 20, int $offset = 0): array
    {
        $builder = $this->publishedBuilder();
        $this->applySearch($builder, $query);

        $rows = $builder
            ->orderBy('published_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit(max(1, min(100, $limit)), max(0, $offset))
            ->get()
            ->getResultArray();

        return array_map(fn (array $row): array => $this->decorate($row), $rows);
    }

    public function countPublished(string $query = ''): int
    {
        $builder = $this->publishedBuilder();
        $this->applySearch($builder, $query);

        return $builder->countAllResults();
    }

    public function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = $this->db->table('news_articles')->where('id', $id)->get()->getRowArray();

        return $row === null ? null : $this->decorate($row);
    }

    public function findPublishedBySlug(string $slug): ?array
    {
        $slug = $this->normalizeSlug($slug, false);
        if ($slug === '') {
            return null;
        }

        $row = $this->publishedBuilder()
            ->where('slug', $slug)
            ->get()
            ->getRowArray();

        return $row === null ? null : $this->decorate($row);
    }

    public function preview(array $data, ?int $id = null): array
    {
        $current = $id === null ? null : $this->find($id);
        if ($id !== null && $current === null) {
            throw new RuntimeException(lang('News.Errors.notFound'));
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

        return $this->workflow()->transaction(function () use ($normalized, $now, $userId): array {
            $this->db->table('news_articles')->insert([
                ...$normalized,
                'created_by' => $this->validUserId($userId),
                'updated_by' => $this->validUserId($userId),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $article = $this->required((int) $this->db->insertID());
            $this->workflow()->record((int) $article['id'], $article, $userId);
            return $article;
        });
    }

    public function update(int $id, array $data, ?int $userId = null): array
    {
        $current = $this->find($id);
        if ($current === null) {
            throw new RuntimeException(lang('News.Errors.notFound'));
        }

        $article = $this->workflow()->transaction(function () use ($id, $data, $current, $userId): array {
            if (! $this->workflow()->hasRevisions($id)) {
                $this->workflow()->record($id, $current, $current['updated_by']);
            }
            $this->db->table('news_articles')
                ->where('id', $id)
                ->update([
                    ...$this->normalize($data, $current),
                    'updated_by' => $this->validUserId($userId),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            $article = $this->required($id);
            $this->workflow()->record($id, $article, $userId);
            return $article;
        });

        if ($current['slug'] !== $article['slug']) {
            Events::trigger(CoreEvents::RESOURCE_MOVED, 'news.article', (string) $id, $this->url($current), $this->url($article));
        }
        return $article;
    }

    public function delete(int $id): void
    {
        if ($id > 0) {
            $this->workflow()->transaction(function () use ($id): void {
                $this->workflow()->delete($id);
                $this->db->table('news_articles')->where('id', $id)->delete();
            });
        }
    }

    public function url(int|array $article): string
    {
        if (is_int($article)) {
            $article = $this->find($article);
            if ($article === null) {
                throw new RuntimeException(lang('News.Errors.notFound'));
            }
        }

        $slug = (string) ($article['slug'] ?? '');
        if ($slug === '') {
            throw new InvalidArgumentException(lang('News.Errors.invalidSlug'));
        }

        return '/news/' . rawurlencode($slug);
    }

    private function workflow(): ContentWorkflow
    {
        return new ContentWorkflow($this->db, 'news');
    }

    private function normalize(array $data, ?array $current): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException(lang('News.Errors.titleRequired'));
        }
        $title = mb_substr($title, 0, 255);

        $slugInput = trim((string) ($data['slug'] ?? ''));
        $slug = $slugInput === ''
            ? $this->slugify($title)
            : $this->normalizeSlug($slugInput, true);
        if ($slug === '') {
            throw new InvalidArgumentException(lang('News.Errors.invalidSlug'));
        }
        $slug = $this->uniqueSlug($slug, $current === null ? null : (int) $current['id']);

        $status = trim((string) ($data['status'] ?? 'draft'));
        if (! in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException(lang('News.Errors.invalidStatus'));
        }

        $body = $this->sanitizer->sanitize((string) ($data['body'] ?? ''));
        $excerpt = trim((string) ($data['excerpt'] ?? ''));
        if ($excerpt === '') {
            $plain = ShortcodeRenderer::stripTokens(html_entity_decode(strip_tags($body)));
            $plain = trim(preg_replace('/\s+/u', ' ', $plain) ?? '');
            $excerpt = $plain === '' ? '' : mb_substr($plain, 0, 320);
        }

        return [
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $excerpt === '' ? null : mb_substr($excerpt, 0, 500),
            'body' => $body,
            'status' => $status,
            'featured_media_id' => $this->normalizeFeaturedMedia($data['featured_media_id'] ?? null),
            'seo_title' => $this->nullableText($data['seo_title'] ?? null, 255),
            'seo_description' => $this->nullableText($data['seo_description'] ?? null, 320),
            'seo_keywords' => $this->nullableText($data['seo_keywords'] ?? null, 1000),
            'tags' => $this->nullableText($data['tags'] ?? null, 1000),
            'published_at' => $this->normalizePublishedAt($status, $data['published_at'] ?? null, $current),
        ];
    }

    private function normalizeFeaturedMedia(mixed $value): ?int
    {
        $id = (int) $value;
        if ($id <= 0) {
            return null;
        }

        $media = $this->media->find($id);
        if ($media === null || ! (bool) ($media['is_image'] ?? false)) {
            throw new InvalidArgumentException(lang('News.Errors.invalidFeaturedMedia'));
        }

        return $id;
    }

    private function normalizePublishedAt(string $status, mixed $value, ?array $current): ?string
    {
        if ($status !== 'published') {
            return null;
        }

        $input = trim((string) $value);
        if ($input === '') {
            if ($current !== null
                && ($current['status'] ?? null) === 'published'
                && ($current['published_at'] ?? null) !== null
            ) {
                return (string) $current['published_at'];
            }

            return date('Y-m-d H:i:s');
        }

        $timestamp = strtotime($input);
        if ($timestamp === false) {
            throw new InvalidArgumentException(lang('News.Errors.invalidPublishedAt'));
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function publishedBuilder(): object
    {
        return $this->db->table('news_articles')
            ->where('status', 'published')
            ->where('published_at IS NOT NULL', null, false)
            ->where('published_at <=', date('Y-m-d H:i:s'));
    }

    private function applySearch(object $builder, string $query): void
    {
        $query = trim($query);
        if ($query === '') {
            return;
        }

        $builder
            ->groupStart()
            ->like('title', $query)
            ->orLike('slug', $query)
            ->orLike('excerpt', $query)
            ->groupEnd();
    }

    private function required(int $id): array
    {
        $article = $this->find($id);
        if ($article === null) {
            throw new RuntimeException(lang('News.Errors.notFound'));
        }

        return $article;
    }

    private function normalizeSlug(string $slug, bool $strict): string
    {
        $slug = trim(strtolower($slug), '-');

        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            if ($strict) {
                throw new InvalidArgumentException(lang('News.Errors.invalidSlug'));
            }

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
                throw new RuntimeException(lang('News.Errors.duplicateSlug'));
            }
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $currentId): bool
    {
        $builder = $this->db->table('news_articles')->where('slug', $slug);
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

        $value = strtr(mb_strtolower($title), $map);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return $this->normalizeSlug(trim($value, '-'), false);
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
        $row['featured_media_id'] = $row['featured_media_id'] === null ? null : (int) $row['featured_media_id'];
        $row['url'] = $this->url($row);
        $row['is_live'] = $row['status'] === 'published'
            && $row['published_at'] !== null
            && strtotime((string) $row['published_at']) <= time();

        $row['featured_media'] = $row['featured_media_id'] === null
            ? null
            : $this->media->find($row['featured_media_id']);
        $row['featured_image_url'] = $row['featured_media'] === null
            ? null
            : $this->media->url($row['featured_media']);

        return $row;
    }
}
