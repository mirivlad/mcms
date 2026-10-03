<?php

declare(strict_types=1);

namespace MCMS\Modules\Catalogue\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Events\Events;
use InvalidArgumentException;
use MCMS\Core\Content\HtmlSanitizer;
use MCMS\Core\Events\CoreEvents;
use MCMS\Modules\Catalogue\Api\CatalogueInterface;
use MCMS\Modules\ProductImages\Api\ProductImagesInterface;
use RuntimeException;
use Throwable;

final class Catalogue implements CatalogueInterface
{
    public function __construct(private readonly BaseConnection $db, private readonly ProductImagesInterface $images)
    {
    }

    public function list(string $query = '', ?string $status = null, int $limit = 24, int $offset = 0, string $category = ''): array
    {
        return array_map(fn (array $row): array => $this->decorate($row), $this->builder($query, $status, $category)
            ->orderBy('id', 'DESC')->limit(max(1, min(100, $limit)), max(0, $offset))->get()->getResultArray());
    }

    public function count(string $query = '', ?string $status = null, string $category = ''): int
    {
        return $this->builder($query, $status, $category)->countAllResults();
    }

    public function categories(bool $publishedOnly = false): array
    {
        return array_column($this->builder('', $publishedOnly ? 'published' : null, '')
            ->select('category')->where('category !=', '')->distinct()->orderBy('category')->get()->getResultArray(), 'category');
    }

    public function find(int $id): ?array
    {
        $row = $this->db->table('catalogue_products')->where('id', $id)->get()->getRowArray();
        return $row === null ? null : $this->decorate($row);
    }

    public function findPublishedBySlug(string $slug): ?array
    {
        $row = $this->db->table('catalogue_products')->where('slug', $slug)->where('status', 'published')->get()->getRowArray();
        return $row === null ? null : $this->decorate($row);
    }

    public static function priceMinor(string $input): int
    {
        $input = str_replace(',', '.', trim($input));
        if (preg_match('/^(0|[1-9][0-9]{0,8})(?:\.([0-9]{1,2}))?$/', $input, $matches) !== 1) {
            throw new InvalidArgumentException(lang('Catalogue.invalidPrice'));
        }
        return (int) $matches[1] * 100 + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    public function save(?int $id, array $data, int $userId): array
    {
        $current = $id === null ? null : ($this->find($id) ?? throw new RuntimeException(lang('Catalogue.notFound')));
        $title = trim((string) ($data['title'] ?? ''));
        $slug = strtolower(trim((string) ($data['slug'] ?? '')));
        $status = (string) ($data['status'] ?? 'draft');
        if ($title === '' || mb_strlen($title) > 255 || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1
            || strlen($slug) > 190 || ! in_array($status, ['draft', 'published'], true)) {
            throw new InvalidArgumentException(lang('Catalogue.invalidProduct'));
        }
        $duplicate = $this->db->table('catalogue_products')->where('slug', $slug)->get()->getRowArray();
        if ($duplicate !== null && (int) $duplicate['id'] !== $id) {
            throw new InvalidArgumentException(lang('Catalogue.duplicateSlug'));
        }
        $setId = (string) ($data['image_set_id'] ?? '');
        $this->images->authorize($setId, $userId);
        if ($current !== null && $setId !== $current['image_set_id']) {
            throw new InvalidArgumentException(lang('ProductImages.usedSet'));
        }
        $rows = $data['variants'] ?? [];
        if (! is_array($rows) || count($rows) < 1 || count($rows) > 50) {
            throw new InvalidArgumentException(lang('Catalogue.invalidVariants'));
        }
        $variants = [];
        $oldIds = array_column($current['variants'] ?? [], 'id');
        $seenIds = [];
        $seenSkus = [];
        foreach (array_values($rows) as $position => $row) {
            if (! is_array($row)) {
                throw new InvalidArgumentException(lang('Catalogue.invalidVariants'));
            }
            $variantId = max(0, (int) ($row['id'] ?? 0));
            $sku = mb_strtoupper(trim((string) ($row['sku'] ?? '')));
            $label = trim((string) ($row['label'] ?? ''));
            if ($sku === '' || mb_strlen($sku) > 100 || mb_strlen($label) > 120
                || in_array(mb_strtolower($sku), $seenSkus, true)
                || ($variantId > 0 && (! in_array($variantId, $oldIds, true) || in_array($variantId, $seenIds, true)))) {
                throw new InvalidArgumentException(lang('Catalogue.invalidVariants'));
            }
            $duplicate = $this->db->table('catalogue_variants')->where('sku', $sku)->get()->getRowArray();
            if ($duplicate !== null && (int) $duplicate['id'] !== $variantId) {
                throw new InvalidArgumentException(lang('Catalogue.duplicateSku'));
            }
            $variants[] = ['id' => $variantId, 'sku' => $sku, 'label' => $label,
                'price_minor' => self::priceMinor((string) ($row['price'] ?? '')), 'currency' => 'RUB', 'position' => $position];
            $seenSkus[] = mb_strtolower($sku);
            $seenIds[] = $variantId;
        }
        $record = ['title' => $title, 'slug' => $slug, 'status' => $status, 'image_set_id' => $setId,
            'category' => mb_substr(trim((string) ($data['category'] ?? '')), 0, 120),
            'summary' => mb_substr(strip_tags((string) ($data['summary'] ?? '')), 0, 2000),
            'body' => (new HtmlSanitizer())->sanitize(mb_substr((string) ($data['body'] ?? ''), 0, 100000)),
            'updated_at' => date('Y-m-d H:i:s')];
        $this->db->transBegin();
        try {
            if ($current === null) {
                $this->images->attach($setId, $userId);
                $this->db->table('catalogue_products')->insert([...$record, 'created_at' => $record['updated_at']]);
                $id = (int) $this->db->insertID();
            } else {
                $this->db->table('catalogue_products')->where('id', $id)->update($record);
                $deletedIds = array_diff($oldIds, $seenIds);
                if ($deletedIds !== []) {
                    $this->db->table('catalogue_variants')->where('product_id', $id)->whereIn('id', $deletedIds)->delete();
                }
            }
            foreach ($variants as $variant) {
                $variantId = $variant['id'];
                unset($variant['id']);
                $variant['product_id'] = $id;
                if ($variantId === 0) {
                    $this->db->table('catalogue_variants')->insert($variant);
                } else {
                    $this->db->table('catalogue_variants')->where('id', $variantId)->update($variant);
                }
            }
            if (! $this->db->transStatus()) {
                throw new RuntimeException(lang('Catalogue.saveFailed'));
            }
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
        $product = $this->find($id);
        if ($current !== null && $current['slug'] !== $slug) {
            Events::trigger(CoreEvents::RESOURCE_MOVED, 'catalogue.product', (string) $id, $current['url'], $product['url']);
        }
        return $product;
    }

    public function delete(int $id): void
    {
        $product = $this->find($id) ?? throw new RuntimeException(lang('Catalogue.notFound'));
        $this->db->transStart();
        $this->db->table('catalogue_variants')->where('product_id', $id)->delete();
        $this->db->table('catalogue_products')->where('id', $id)->delete();
        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            throw new RuntimeException(lang('Catalogue.saveFailed'));
        }
        $this->images->deleteSet($product['image_set_id']);
    }

    private function builder(string $query, ?string $status, string $category): object
    {
        $builder = $this->db->table('catalogue_products');
        if (trim($query) !== '') {
            $builder->groupStart()->like('title', trim($query))->orLike('summary', trim($query))->groupEnd();
        }
        if (in_array($status, ['draft', 'published'], true)) {
            $builder->where('status', $status);
        }
        if ($category !== '') {
            $builder->where('category', $category);
        }
        return $builder;
    }

    private function decorate(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['url'] = '/shop/' . $row['slug'];
        $row['updated_at'] = max($row['updated_at'], $this->images->updatedAt($row['image_set_id']));
        $row['images'] = $this->images->images($row['image_set_id']);
        $row['main_image'] = null;
        foreach ($row['images'] as &$image) {
            foreach (['thumb', 'display', 'full'] as $size) {
                $image[$size . '_url'] = '/shop/' . $row['slug'] . '/images/' . $image['id'] . '/' . $size . '?v=' . $image['revision'];
            }
            if ($image['main']) {
                $row['main_image'] = $image;
            }
        }
        unset($image);
        $row['variants'] = array_map(static function (array $variant): array {
            $variant['id'] = (int) $variant['id'];
            $variant['price_minor'] = (int) $variant['price_minor'];
            $variant['price'] = intdiv($variant['price_minor'], 100) . '.' . str_pad((string) ($variant['price_minor'] % 100), 2, '0', STR_PAD_LEFT);
            $variant['price_display'] = number_format(intdiv($variant['price_minor'], 100), 0, ',', ' ')
                . ',' . str_pad((string) ($variant['price_minor'] % 100), 2, '0', STR_PAD_LEFT) . ' ₽';
            return $variant;
        }, $this->db->table('catalogue_variants')->where('product_id', $row['id'])->orderBy('position')->get()->getResultArray());
        $row['from_price'] = $row['variants'] === [] ? '' : array_reduce($row['variants'],
            static fn (?array $min, array $variant): array => $min === null || $variant['price_minor'] < $min['price_minor'] ? $variant : $min)['price_display'];
        return $row;
    }
}
