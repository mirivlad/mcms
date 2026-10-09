<?php

declare(strict_types=1);

namespace MCMS\Modules\Catalogue\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Events\Events;
use InvalidArgumentException;
use MCMS\Core\Content\HtmlSanitizer;
use MCMS\Core\Events\CoreEvents;
use MCMS\Modules\Catalogue\Api\CatalogueInterface;
use MCMS\Modules\Catalogue\Api\VariantFeedInterface;
use MCMS\Modules\ProductImages\Api\ProductImagesInterface;
use RuntimeException;
use Throwable;

final class Catalogue implements CatalogueInterface, VariantFeedInterface
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
        return (new Categories($this->db))->list($publishedOnly);
    }

    public function variants(string $query = '', int $limit = 50, int $offset = 0): array
    {
        $builder = $this->variantBuilder($query);
        $rows = $builder->orderBy('p.title', 'ASC')->orderBy('v.position', 'ASC')
            ->limit(max(1, min(200, $limit)), max(0, $offset))->get()->getResultArray();

        return array_map(fn (array $row): array => $this->decorateVariant($row), $rows);
    }

    public function countVariants(string $query = ''): int
    {
        return $this->variantBuilder($query)->countAllResults();
    }

    public function variantPage(int $afterId = 0, int $limit = 100, ?int $untilId = null): array
    {
        if ($afterId < 0 || $limit < 1 || $limit > 200 || ($untilId !== null && $untilId < $afterId)) {
            throw new InvalidArgumentException('Invalid variant feed cursor or limit.');
        }
        $untilId ??= max($afterId, (int) ($this->db->table('catalogue_variants')->selectMax('id')->get()->getRowArray()['id'] ?? 0));
        $rows = $this->variantBuilder('')
            ->where('v.id >', $afterId)->where('v.id <=', $untilId)->orderBy('v.id', 'ASC')
            ->limit($limit + 1)->get()->getResultArray();
        $more = count($rows) > $limit;
        $rows = array_map(fn (array $row): array => $this->decorateVariant($row), array_slice($rows, 0, $limit));
        return ['items' => $rows, 'next_cursor' => $rows === [] ? $afterId : (int) end($rows)['id'], 'until_id' => $untilId, 'has_more' => $more];
    }

    public function findVariant(int $id): ?array
    {
        $row = $this->db->table('catalogue_variants v')
            ->select('v.*, p.title AS product_title, p.slug AS product_slug, p.status AS product_status')
            ->join('catalogue_products p', 'p.id = v.product_id')
            ->where('v.id', $id)->get()->getRowArray();

        return $row === null ? null : $this->decorateVariant($row);
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
        $categoryId = trim((string) ($data['category_id'] ?? ''));
        $category = $categoryId === '' ? null : (new Categories($this->db))->find((int) $categoryId);
        if ($categoryId !== '' && (preg_match('/^[1-9][0-9]*$/', $categoryId) !== 1 || $category === null)) {
            throw new InvalidArgumentException(lang('Catalogue.categoryNotFound'));
        }
        $record = ['title' => $title, 'slug' => $slug, 'status' => $status, 'image_set_id' => $setId,
            'category_id' => $category['id'] ?? null, 'category' => $category['name'] ?? '',
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

    private function variantBuilder(string $query): object
    {
        $builder = $this->db->table('catalogue_variants v')
            ->select('v.*, p.title AS product_title, p.slug AS product_slug, p.status AS product_status')
            ->join('catalogue_products p', 'p.id = v.product_id');
        $query = trim($query);
        if ($query !== '') {
            $builder->groupStart()
                ->like('p.title', $query)
                ->orLike('v.sku', $query)
                ->orLike('v.label', $query)
                ->groupEnd();
        }

        return $builder;
    }

    private function decorateVariant(array $variant): array
    {
        $variant['id'] = (int) $variant['id'];
        $variant['product_id'] = (int) $variant['product_id'];
        $variant['price_minor'] = (int) $variant['price_minor'];
        $variant['price'] = intdiv($variant['price_minor'], 100) . '.' . str_pad((string) ($variant['price_minor'] % 100), 2, '0', STR_PAD_LEFT);
        $variant['price_display'] = number_format(intdiv($variant['price_minor'], 100), 0, ',', ' ')
            . ',' . str_pad((string) ($variant['price_minor'] % 100), 2, '0', STR_PAD_LEFT) . ' ₽';
        if (isset($variant['product_slug'])) {
            $variant['product_url'] = '/shop/' . $variant['product_slug'];
        }

        return $variant;
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
            $categories = new Categories($this->db);
            $selected = $categories->bySlug($category);
            if ($selected === null) {
                // Old bookmarks used a label in the query string.
                $row = $this->db->table('catalogue_categories')->where('name', $category)->get()->getRowArray();
                $selected = $row;
            }
            $builder->whereIn('category_id', $selected === null ? [0] : $categories->descendants((int) $selected['id']));
        }
        return $builder;
    }

    private function decorate(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['category_id'] = $row['category_id'] === null ? null : (int) $row['category_id'];
        $row['category_url'] = $row['category_id'] === null ? null : (new Categories($this->db))->find($row['category_id'])['url'];
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
        $row['variants'] = array_map(
            fn (array $variant): array => $this->decorateVariant($variant),
            $this->db->table('catalogue_variants')->where('product_id', $row['id'])->orderBy('position')->get()->getResultArray()
        );
        $row['from_price'] = $row['variants'] === [] ? '' : array_reduce($row['variants'],
            static fn (?array $min, array $variant): array => $min === null || $variant['price_minor'] < $min['price_minor'] ? $variant : $min)['price_display'];
        return $row;
    }
}
