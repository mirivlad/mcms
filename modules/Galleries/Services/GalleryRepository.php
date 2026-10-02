<?php

declare(strict_types=1);

namespace MCMS\Modules\Galleries\Services;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use MCMS\Modules\Galleries\Api\GalleryRepositoryInterface;
use MCMS\Modules\Media\Api\MediaLibraryInterface;
use RuntimeException;
use Throwable;
use CodeIgniter\Events\Events;
use MCMS\Core\Events\CoreEvents;

final class GalleryRepository implements GalleryRepositoryInterface
{
    public function __construct(private readonly BaseConnection $db, private readonly MediaLibraryInterface $media)
    {
    }

    public function list(string $query = '', ?string $status = null, int $limit = 100, int $offset = 0): array
    {
        return array_map(fn (array $row): array => $this->decorate($row), $this->builder($query, $status)
            ->orderBy('updated_at', 'DESC')->orderBy('id', 'DESC')
            ->limit(max(1, min(250, $limit)), max(0, $offset))->get()->getResultArray());
    }

    public function count(string $query = '', ?string $status = null): int
    {
        return $this->builder($query, $status)->countAllResults();
    }

    public function listPublished(string $query = '', int $limit = 20, int $offset = 0): array
    {
        return $this->list($query, 'published', $limit, $offset);
    }

    public function countPublished(string $query = ''): int
    {
        return $this->count($query, 'published');
    }

    public function find(int $id): ?array
    {
        $row = $this->db->table('galleries')->where('id', $id)->get()->getRowArray();
        return $row === null ? null : $this->decorate($row);
    }

    public function findPublishedBySlug(string $slug): ?array
    {
        $row = $this->db->table('galleries')->where('slug', $slug)->where('status', 'published')->get()->getRowArray();
        return $row === null ? null : $this->decorate($row);
    }

    public function create(array $data, ?int $userId = null): array
    {
        return $this->save(null, $data, $userId);
    }

    public function update(int $id, array $data, ?int $userId = null): array
    {
        $current = $this->find($id);
        if ($current === null) {
            throw new RuntimeException(lang('Galleries.Errors.notFound'));
        }
        return $this->save($current, $data, $userId);
    }

    public function delete(int $id): void
    {
        $this->db->transBegin();
        try {
            $this->db->table('gallery_images')->where('gallery_id', $id)->delete();
            $this->db->table('galleries')->where('id', $id)->delete();
            $this->commit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function url(int|array $album): string
    {
        if (is_int($album)) {
            $album = $this->find($album);
        }
        if ($album === null || ($album['slug'] ?? '') === '') {
            throw new RuntimeException(lang('Galleries.Errors.notFound'));
        }
        return '/galleries/' . rawurlencode($album['slug']);
    }

    private function save(?array $current, array $data, ?int $userId): array
    {
        $title = mb_substr(trim((string) ($data['title'] ?? '')), 0, 255);
        if ($title === '') {
            throw new InvalidArgumentException(lang('Galleries.Errors.titleRequired'));
        }
        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = $this->slugify($title);
        }
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1 || strlen($slug) > 190) {
            throw new InvalidArgumentException(lang('Galleries.Errors.invalidSlug'));
        }
        $base = $slug;
        $suffix = 2;
        while ($this->db->table('galleries')->where('slug', $slug)->where('id !=', (int) ($current['id'] ?? 0))->countAllResults() > 0) {
            $ending = '-' . $suffix++;
            $slug = substr($base, 0, 190 - strlen($ending)) . $ending;
            if ($suffix > 10000) {
                throw new RuntimeException(lang('Galleries.Errors.duplicateSlug'));
            }
        }
        $status = (string) ($data['status'] ?? 'draft');
        if (! in_array($status, ['draft', 'published'], true)) {
            throw new InvalidArgumentException(lang('Galleries.Errors.invalidStatus'));
        }
        $ids = array_values(array_unique(array_map('intval', (array) ($data['media_ids'] ?? []))));
        if (count($ids) > 250) {
            throw new InvalidArgumentException(lang('Galleries.Errors.tooManyImages'));
        }
        $images = [];
        $existingIds = array_column($current['images'] ?? [], 'media_id');
        foreach ($ids as $index => $id) {
            $file = $this->media->find($id);
            if ($id <= 0 || ($file === null && ! in_array($id, $existingIds, true))
                || ($file !== null && ! $file['is_image'])) {
                throw new InvalidArgumentException(lang('Galleries.Errors.invalidImage'));
            }
            $images[] = ['media_id' => $id,
                'caption' => mb_substr(trim((string) ($data['captions'][$id] ?? '')), 0, 500),
                'position' => max(0, (int) ($data['positions'][$id] ?? $index)),
            ];
        }
        usort($images, static fn (array $a, array $b): int => $a['position'] <=> $b['position']);
        $cover = (int) ($data['cover_media_id'] ?? 0);
        if ($cover > 0 && ! in_array($cover, $ids, true)) {
            throw new InvalidArgumentException(lang('Galleries.Errors.invalidCover'));
        }
        $record = [
            'title' => $title, 'slug' => $slug, 'description' => mb_substr(trim((string) ($data['description'] ?? '')), 0, 20000),
            'status' => $status, 'cover_media_id' => $cover > 0 ? $cover : null,
            'updated_by' => $userId !== null && $userId > 0 ? $userId : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $this->db->transBegin();
        try {
            if ($current === null) {
                $this->db->table('galleries')->insert([...$record, 'created_by' => $record['updated_by'], 'created_at' => $record['updated_at']]);
                $id = (int) $this->db->insertID();
            } else {
                $id = (int) $current['id'];
                $this->db->table('galleries')->where('id', $id)->update($record);
                $this->db->table('gallery_images')->where('gallery_id', $id)->delete();
            }
            foreach ($images as $position => $image) {
                $this->db->table('gallery_images')->insert([...$image, 'gallery_id' => $id, 'position' => $position]);
            }
            $this->commit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
        $album = $this->find($id) ?? throw new RuntimeException(lang('Galleries.Errors.notFound'));
        if ($current !== null && $current['slug'] !== $album['slug']) {
            Events::trigger(CoreEvents::RESOURCE_MOVED, 'galleries.album', (string) $id, $this->url($current), $this->url($album));
        }
        return $album;
    }

    private function commit(): void
    {
        if (! $this->db->transStatus()) {
            throw new RuntimeException(lang('Galleries.Errors.saveFailed'));
        }
        $this->db->transCommit();
    }

    private function builder(string $query, ?string $status): object
    {
        $builder = $this->db->table('galleries');
        if (trim($query) !== '') {
            $builder->groupStart()->like('title', trim($query))->orLike('slug', trim($query))->groupEnd();
        }
        if (in_array($status, ['draft', 'published'], true)) {
            $builder->where('status', $status);
        }
        return $builder;
    }

    private function decorate(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['cover_media_id'] = $row['cover_media_id'] === null ? null : (int) $row['cover_media_id'];
        $row['url'] = $this->url($row);
        $row['is_live'] = $row['status'] === 'published';
        $row['images'] = $this->db->table('gallery_images')->where('gallery_id', $row['id'])->orderBy('position')->orderBy('media_id')->get()->getResultArray();
        $row['cover'] = null;
        foreach ($row['images'] as &$image) {
            $image['media_id'] = (int) $image['media_id'];
            $image['position'] = (int) $image['position'];
            $image['media'] = $this->media->find($image['media_id']);
            if ($image['media'] !== null && ($row['cover'] === null || $image['media_id'] === $row['cover_media_id'])) {
                $row['cover'] = $image['media'];
            }
        }
        unset($image);
        return $row;
    }

    private function slugify(string $title): string
    {
        $map = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y',
            'к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f',
            'х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'];
        $value = preg_replace('/[^a-z0-9]+/', '-', strtr(mb_strtolower($title), $map)) ?? '';
        return rtrim(substr(trim($value, '-'), 0, 190), '-');
    }
}
