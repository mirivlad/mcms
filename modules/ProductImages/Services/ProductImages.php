<?php

declare(strict_types=1);

namespace MCMS\Modules\ProductImages\Services;

use CodeIgniter\Database\BaseConnection;
use GdImage;
use InvalidArgumentException;
use MCMS\Modules\ProductImages\Api\ProductImagesInterface;
use RuntimeException;
use Throwable;

final class ProductImages implements ProductImagesInterface
{
    public function __construct(private readonly BaseConnection $db, private readonly string $root)
    {
    }

    public function createSet(int $userId): string
    {
        if ($userId < 1) {
            throw new InvalidArgumentException(lang('ProductImages.denied'));
        }
        foreach ($this->db->table('product_image_sets')->where('attached', 0)
            ->where('updated_at <', date('Y-m-d H:i:s', time() - 86400))->get()->getResultArray() as $row) {
            $this->deleteSet($row['id']);
        }
        $id = bin2hex(random_bytes(16));
        $this->db->table('product_image_sets')->insert([
            'id' => $id, 'owner_id' => $userId, 'attached' => 0, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $id;
    }

    public function authorize(string $setId, int $userId): void
    {
        $set = $this->set($setId);
        if (! (bool) $set['attached'] && (int) $set['owner_id'] !== $userId) {
            throw new RuntimeException(lang('ProductImages.denied'));
        }
    }

    public function attach(string $setId, int $userId): void
    {
        $this->authorize($setId, $userId);
        if ((bool) $this->set($setId)['attached']) {
            throw new InvalidArgumentException(lang('ProductImages.usedSet'));
        }
        $this->db->table('product_image_sets')->where('id', $setId)->update(['attached' => 1]);
    }

    public function updatedAt(string $setId): string
    {
        return $this->set($setId)['updated_at'];
    }

    public function images(string $setId): array
    {
        $set = $this->set($setId);
        return array_map(static function (array $row) use ($set): array {
            foreach (['id', 'width', 'height', 'position'] as $key) {
                $row[$key] = (int) $row[$key];
            }
            $row['recipe'] = json_decode($row['recipe'], true, 512, JSON_THROW_ON_ERROR);
            $row['main'] = $row['id'] === (int) $set['main_id'];
            unset($row['original'], $row['mime']);
            return $row;
        }, $this->db->table('product_images')->where('set_id', $setId)->orderBy('position')->orderBy('id')->get()->getResultArray());
    }

    public function upload(string $setId, string $file, string $name, ?int $replaceId = null): array
    {
        $this->set($setId);
        $current = $replaceId === null ? null : $this->image($setId, $replaceId);
        $images = $this->images($setId);
        if ($current === null && count($images) >= 20) {
            throw new InvalidArgumentException(lang('ProductImages.limit'));
        }
        $info = @getimagesize($file);
        if (! is_file($file) || filesize($file) > 10 * 1024 * 1024 || $info === false
            || ! in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)
            || $info[0] * $info[1] > 12000000 || min($info[0], $info[1]) < 16) {
            throw new InvalidArgumentException(lang('ProductImages.invalidFile'));
        }
        $source = $this->load($file, $info['mime']);
        $width = imagesx($source);
        $height = imagesy($source);
        $recipe = ['x' => 0, 'y' => 0, 'width' => $width, 'height' => $height, 'size' => 1600];
        $dir = $this->directory($setId);
        $original = bin2hex(random_bytes(16)) . '.original';
        $revision = bin2hex(random_bytes(16));
        try {
            if (! copy($file, $dir . '/' . $original)) {
                throw new RuntimeException(lang('ProductImages.saveFailed'));
            }
            $this->render($source, $recipe, $dir, $revision);
            $this->db->transBegin();
            $record = ['set_id' => $setId, 'name' => mb_substr(mb_scrub(basename($name)), 0, 255),
                'original' => $original, 'mime' => $info['mime'], 'width' => $width, 'height' => $height,
                'recipe' => json_encode($recipe), 'revision' => $revision,
                'position' => $current === null ? ($images === [] ? 0 : max(array_column($images, 'position')) + 1) : (int) $current['position']];
            if ($current === null) {
                $this->db->table('product_images')->insert($record);
                $id = (int) $this->db->insertID();
            } else {
                $id = $replaceId;
                $this->db->table('product_images')->where('id', $id)->update($record);
            }
            if ($images === []) {
                $this->db->table('product_image_sets')->where('id', $setId)->update(['main_id' => $id]);
            }
            $this->touch($setId);
            $this->commit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            @unlink($dir . '/' . $original);
            $this->unlinkRevision($dir, $revision);
            throw $e;
        } finally {
            imagedestroy($source);
        }
        if ($current !== null) {
            $this->unlinkImage($setId, $current);
        }
        return $this->images($setId);
    }

    public function crop(string $setId, int $imageId, array $recipe): array
    {
        $image = $this->image($setId, $imageId);
        $clean = [];
        foreach (['x', 'y', 'width', 'height', 'size'] as $key) {
            $value = filter_var($recipe[$key] ?? null, FILTER_VALIDATE_INT);
            if ($value === false) {
                throw new InvalidArgumentException(lang('ProductImages.invalidCrop'));
            }
            $clean[$key] = $value;
        }
        if ($clean['x'] < 0 || $clean['y'] < 0 || $clean['width'] < 16 || $clean['height'] < 16
            || $clean['x'] + $clean['width'] > $image['width'] || $clean['y'] + $clean['height'] > $image['height']
            || $clean['size'] < 300 || $clean['size'] > 2400) {
            throw new InvalidArgumentException(lang('ProductImages.invalidCrop'));
        }
        $dir = $this->directory($setId);
        $revision = bin2hex(random_bytes(16));
        $source = $this->load($dir . '/' . $image['original'], $image['mime']);
        try {
            $this->render($source, $clean, $dir, $revision);
            $this->db->transBegin();
            if (! $this->db->table('product_images')->where('id', $imageId)
                ->update(['recipe' => json_encode($clean), 'revision' => $revision])) {
                throw new RuntimeException(lang('ProductImages.saveFailed'));
            }
            $this->touch($setId);
            $this->commit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            $this->unlinkRevision($dir, $revision);
            throw $e;
        } finally {
            imagedestroy($source);
        }
        $this->unlinkRevision($dir, $image['revision']);
        return $this->images($setId);
    }

    public function arrange(string $setId, array $ids, int $mainId): void
    {
        $expected = array_column($this->images($setId), 'id');
        $ids = array_map('intval', $ids);
        $sorted = $ids;
        sort($sorted);
        sort($expected);
        if ($sorted !== $expected || ($expected !== [] && ! in_array($mainId, $expected, true))
            || ($expected === [] && $mainId !== 0)) {
            throw new InvalidArgumentException(lang('ProductImages.staleOrder'));
        }
        $this->db->transBegin();
        try {
            foreach ($ids as $position => $id) {
                $this->db->table('product_images')->where('set_id', $setId)->where('id', $id)->update(['position' => $position]);
            }
            $this->db->table('product_image_sets')->where('id', $setId)->update(['main_id' => $mainId ?: null, 'updated_at' => date('Y-m-d H:i:s')]);
            $this->commit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function remove(string $setId, int $imageId): void
    {
        $image = $this->image($setId, $imageId);
        $remaining = array_values(array_filter($this->images($setId), static fn (array $row): bool => $row['id'] !== $imageId));
        $mainId = 0;
        foreach ($remaining as $row) {
            if ($mainId === 0 || $row['main']) {
                $mainId = $row['id'];
            }
        }
        $this->db->transBegin();
        try {
            $this->db->table('product_images')->where('id', $imageId)->delete();
            $this->db->table('product_image_sets')->where('id', $setId)->update(['main_id' => $mainId ?: null, 'updated_at' => date('Y-m-d H:i:s')]);
            $this->commit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
        $this->unlinkImage($setId, $image);
    }

    public function deleteSet(string $setId): void
    {
        $this->set($setId);
        $rows = $this->db->table('product_images')->where('set_id', $setId)->get()->getResultArray();
        $this->db->transBegin();
        try {
            $this->db->table('product_images')->where('set_id', $setId)->delete();
            $this->db->table('product_image_sets')->where('id', $setId)->delete();
            $this->commit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
        foreach ($rows as $row) {
            $this->unlinkImage($setId, $row);
        }
        @rmdir($this->root . '/' . $setId);
    }

    public function file(string $setId, int $imageId, string $size): ?array
    {
        if (! in_array($size, ['original', 'thumb', 'display', 'full'], true)) {
            return null;
        }
        $row = $this->db->table('product_images')->where('set_id', $setId)->where('id', $imageId)->get()->getRowArray();
        if ($row === null || preg_match('/^[a-f0-9]{32}$/', $setId) !== 1) {
            return null;
        }
        // The editor sees an oriented preview; raw original bytes remain private.
        $variant = $size === 'original' ? 'editor' : $size;
        $path = $this->root . '/' . $setId . '/' . $row['revision'] . '-' . $variant . '.webp';
        return is_file($path) ? ['path' => $path, 'mime' => 'image/webp'] : null;
    }

    private function set(string $id): array
    {
        if (preg_match('/^[a-f0-9]{32}$/', $id) !== 1) {
            throw new InvalidArgumentException(lang('ProductImages.notFound'));
        }
        return $this->db->table('product_image_sets')->where('id', $id)->get()->getRowArray()
            ?? throw new InvalidArgumentException(lang('ProductImages.notFound'));
    }

    private function image(string $setId, int $id): array
    {
        $this->set($setId);
        return $this->db->table('product_images')->where('set_id', $setId)->where('id', $id)->get()->getRowArray()
            ?? throw new InvalidArgumentException(lang('ProductImages.notFound'));
    }

    private function directory(string $setId): string
    {
        $dir = $this->root . '/' . $setId;
        if (! is_dir($dir) && ! mkdir($dir, 0750, true) && ! is_dir($dir)) {
            throw new RuntimeException(lang('ProductImages.saveFailed'));
        }
        return $dir;
    }

    private function load(string $path, string $mime): GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };
        if (! $image instanceof GdImage) {
            throw new InvalidArgumentException(lang('ProductImages.invalidFile'));
        }
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);
            if (in_array($orientation, [2, 4, 5, 7], true)) {
                imageflip($image, in_array($orientation, [4, 5], true) ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
            }
            $angle = match ($orientation) {3, 4 => 180, 5, 6 => -90, 7, 8 => 90, default => 0};
            if ($angle !== 0) {
                $rotated = imagerotate($image, $angle, 0);
                imagedestroy($image);
                $image = $rotated;
            }
        }
        return $image;
    }

    private function render(GdImage $source, array $recipe, string $dir, string $revision): void
    {
        foreach (['thumb' => 240, 'display' => 800, 'full' => $recipe['size'], 'editor' => 1600] as $name => $size) {
            $crop = $name === 'editor' ? ['x' => 0, 'y' => 0, 'width' => imagesx($source), 'height' => imagesy($source)] : $recipe;
            $scale = min(1, $size / max($crop['width'], $crop['height']), ($name === 'editor' ? 1600 : $recipe['size']) / max($crop['width'], $crop['height']));
            $width = max(1, (int) round($crop['width'] * $scale));
            $height = max(1, (int) round($crop['height'] * $scale));
            $output = imagecreatetruecolor($width, $height);
            imagealphablending($output, false);
            imagesavealpha($output, true);
            imagecopyresampled($output, $source, 0, 0, $crop['x'], $crop['y'], $width, $height, $crop['width'], $crop['height']);
            $ok = imagewebp($output, $dir . '/' . $revision . '-' . $name . '.webp', 88);
            imagedestroy($output);
            if (! $ok) {
                throw new RuntimeException(lang('ProductImages.saveFailed'));
            }
        }
    }

    private function unlinkImage(string $setId, array $row): void
    {
        $dir = $this->root . '/' . $setId;
        @unlink($dir . '/' . $row['original']);
        $this->unlinkRevision($dir, $row['revision']);
    }

    private function unlinkRevision(string $dir, string $revision): void
    {
        foreach (['thumb', 'display', 'full', 'editor'] as $size) {
            @unlink($dir . '/' . $revision . '-' . $size . '.webp');
        }
    }

    private function touch(string $setId): void
    {
        $this->db->table('product_image_sets')->where('id', $setId)->update(['updated_at' => date('Y-m-d H:i:s')]);
    }

    private function commit(): void
    {
        if (! $this->db->transStatus()) {
            throw new RuntimeException(lang('ProductImages.saveFailed'));
        }
        $this->db->transCommit();
    }
}
