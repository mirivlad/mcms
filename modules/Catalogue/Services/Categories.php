<?php

declare(strict_types=1);

namespace MCMS\Modules\Catalogue\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Events\Events;
use InvalidArgumentException;
use MCMS\Core\Events\CoreEvents;
use RuntimeException;
use Throwable;

final class Categories
{
    public function __construct(private readonly BaseConnection $db, private readonly string $directory = WRITEPATH . 'product-images/category-icons')
    {
    }

    public static function icons(): array
    {
        static $icons;
        return $icons ??= json_decode(file_get_contents(dirname(__DIR__) . '/assets/tabler-icons.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    /** Categories in tree order, with their depth and full readable path. */
    public function list(bool $publishedOnly = false): array
    {
        $rows = $this->db->table('catalogue_categories')->orderBy('name')->get()->getResultArray();
        $result = [];
        $walk = function (?int $parent, int $depth, string $path) use (&$walk, &$result, $rows): void {
            foreach ($rows as $row) {
                if (($row['parent_id'] === null ? null : (int) $row['parent_id']) !== $parent) {
                    continue;
                }
                $row = $this->decorate($row);
                $row['depth'] = $depth;
                $row['path'] = $path === '' ? $row['name'] : $path . ' / ' . $row['name'];
                $result[] = $row;
                $walk($row['id'], $depth + 1, $row['path']);
            }
        };
        $walk(null, 0, '');
        if (! $publishedOnly) {
            return $result;
        }
        $used = array_map('intval', array_column($this->db->table('catalogue_products')->select('category_id')
            ->where('status', 'published')->where('category_id !=', null)->distinct()->get()->getResultArray(), 'category_id'));
        $visible = $used;
        foreach (array_reverse($result) as $row) {
            if (in_array($row['id'], $visible, true) && $row['parent_id'] !== null) {
                $visible[] = $row['parent_id'];
            }
        }
        return array_values(array_filter($result, static fn (array $row): bool => in_array($row['id'], $visible, true)));
    }

    public function find(int $id): ?array
    {
        $row = $this->db->table('catalogue_categories')->where('id', $id)->get()->getRowArray();
        return $row === null ? null : $this->decorate($row);
    }

    public function bySlug(string $slug): ?array
    {
        $row = $this->db->table('catalogue_categories')->where('slug', $slug)->get()->getRowArray();
        return $row === null ? null : $this->decorate($row);
    }

    public function descendants(int $id): array
    {
        $ids = [$id];
        foreach ($this->list() as $row) {
            if (in_array($row['parent_id'], $ids, true)) {
                $ids[] = $row['id'];
            }
        }
        return $ids;
    }

    public function save(?int $id, array $data, ?string $upload = null): array
    {
        $current = $id === null ? null : ($this->find($id) ?? throw new RuntimeException(lang('Catalogue.categoryNotFound')));
        $name = trim((string) ($data['name'] ?? ''));
        $slug = strtolower(trim((string) ($data['slug'] ?? '')));
        $parent = trim((string) ($data['parent_id'] ?? ''));
        $type = (string) ($data['icon_type'] ?? 'tabler');
        $icon = (string) ($data['icon'] ?? 'folder');
        $color = strtolower((string) ($data['icon_color'] ?? '#206bc4'));
        if ($name === '' || mb_strlen($name) > 120 || strlen($slug) > 190
            || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1
            || ! in_array($type, ['tabler', 'image'], true) || ! in_array($icon, self::icons(), true)
            || preg_match('/^#[0-9a-f]{6}$/', $color) !== 1
            || ($parent !== '' && (preg_match('/^[1-9][0-9]*$/', $parent) !== 1 || $this->find((int) $parent) === null))) {
            throw new InvalidArgumentException(lang('Catalogue.invalidCategory'));
        }
        $parentId = $parent === '' ? null : (int) $parent;
        if ($id !== null && $parentId !== null && in_array($parentId, $this->descendants($id), true)) {
            throw new InvalidArgumentException(lang('Catalogue.categoryCycle'));
        }
        $duplicate = $this->bySlug($slug);
        if ($duplicate !== null && $duplicate['id'] !== $id) {
            throw new InvalidArgumentException(lang('Catalogue.duplicateCategorySlug'));
        }
        $image = $type === 'image' ? ($current['image'] ?? null) : null;
        $newImage = null;
        if ($type === 'image' && $upload !== null) {
            $image = $newImage = $this->storeImage($upload);
        }
        if ($type === 'image' && $image === null) {
            throw new InvalidArgumentException(lang('Catalogue.categoryImageRequired'));
        }
        $record = ['name' => $name, 'slug' => $slug, 'parent_id' => $parentId, 'icon_type' => $type,
            'icon' => $icon, 'icon_color' => $color, 'image' => $image, 'updated_at' => date('Y-m-d H:i:s')];
        $this->db->transBegin();
        try {
            if ($id === null) {
                $this->db->table('catalogue_categories')->insert($record);
                $id = (int) $this->db->insertID();
            } else {
                $this->db->table('catalogue_categories')->where('id', $id)->update($record);
                $this->db->table('catalogue_products')->where('category_id', $id)->update(['category' => $name]);
            }
            if (! $this->db->transStatus()) {
                throw new RuntimeException(lang('Catalogue.saveFailed'));
            }
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            if ($newImage !== null) {
                unlink($this->directory . '/' . $newImage);
            }
            throw $e;
        }
        if (($current['image'] ?? null) !== null && $current['image'] !== $image) {
            $this->removeImage($current['image']);
        }
        $saved = $this->find($id);
        if ($current !== null && $current['slug'] !== $slug) {
            Events::trigger(CoreEvents::RESOURCE_MOVED, 'catalogue.category', (string) $id, $current['url'], $saved['url']);
        }
        return $saved;
    }

    public function delete(int $id): void
    {
        $category = $this->find($id) ?? throw new RuntimeException(lang('Catalogue.categoryNotFound'));
        if ($this->db->table('catalogue_categories')->where('parent_id', $id)->countAllResults() > 0
            || $this->db->table('catalogue_products')->where('category_id', $id)->countAllResults() > 0) {
            throw new InvalidArgumentException(lang('Catalogue.categoryInUse'));
        }
        $this->db->table('catalogue_categories')->where('id', $id)->delete();
        if ($category['image'] !== null) {
            $this->removeImage($category['image']);
        }
    }

    public function imageFile(int $id): ?string
    {
        $image = $this->find($id)['image'] ?? null;
        if ($image === null || preg_match('/^[a-f0-9]{32}\.webp$/', $image) !== 1) {
            return null;
        }
        $path = $this->directory . '/' . $image;
        return is_file($path) ? $path : null;
    }

    private function decorate(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['parent_id'] = $row['parent_id'] === null ? null : (int) $row['parent_id'];
        $row['url'] = '/shop/category/' . $row['slug'];
        $row['image_url'] = $row['image'] === null ? null : '/shop/category-icons/' . $row['id'] . '?v=' . substr($row['image'], 0, 32);
        return $row;
    }

    private function storeImage(string $path): string
    {
        $size = is_file($path) ? filesize($path) : 0;
        $info = $size > 0 && $size <= 5 * 1024 * 1024 ? @getimagesize($path) : false;
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            || $info[0] * $info[1] > 12000000) {
            throw new InvalidArgumentException(lang('Catalogue.invalidCategoryImage'));
        }
        $source = @imagecreatefromstring(file_get_contents($path));
        if ($source === false) {
            throw new InvalidArgumentException(lang('Catalogue.invalidCategoryImage'));
        }
        $ratio = min(1, 256 / max($info[0], $info[1]));
        $width = max(1, (int) round($info[0] * $ratio));
        $height = max(1, (int) round($info[1] * $ratio));
        $target = imagecreatetruecolor($width, $height);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
        $name = bin2hex(random_bytes(16)) . '.webp';
        try {
            if (! is_dir($this->directory) && ! mkdir($this->directory, 0755, true) && ! is_dir($this->directory)) {
                throw new RuntimeException(lang('Catalogue.saveFailed'));
            }
            if (! imagewebp($target, $this->directory . '/' . $name, 90)) {
                throw new RuntimeException(lang('Catalogue.saveFailed'));
            }
        } finally {
            imagedestroy($source);
            imagedestroy($target);
        }
        return $name;
    }

    private function removeImage(string $name): void
    {
        if (preg_match('/^[a-f0-9]{32}\.webp$/', $name) === 1 && is_file($this->directory . '/' . $name)) {
            unlink($this->directory . '/' . $name);
        }
    }
}
