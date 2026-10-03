<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Core\Navigation\NavigableResourceRegistry;
use MCMS\Modules\Catalogue\Services\Catalogue;
use MCMS\Modules\ProductImages\Services\ProductImages;

final class CatalogueModuleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\\Settings'];
    private string $directory;
    private ProductImages $images;
    private Catalogue $catalogue;
    private ModuleManager $manager;
    private ModuleServiceRegistry $services;
    private NavigableResourceRegistry $navigation;

    protected function setUp(): void
    {
        parent::setUp();
        service('language')->setLocale('en');
        $this->directory = sys_get_temp_dir() . '/mcms-product-test-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
        $this->services = new ModuleServiceRegistry();
        $this->navigation = new NavigableResourceRegistry();
        $this->manager = new ModuleManager($this->db, new AdminRegistry(), $this->services, ROOTPATH . 'modules', $this->navigation);
        $this->manager->enable('catalogue');
        $this->manager->bootEnabled();
        $this->images = new ProductImages($this->db, $this->directory);
        $this->catalogue = new Catalogue($this->db, $this->images);
    }

    protected function tearDown(): void
    {
        $this->migrations->setNamespace('MCMS\\Modules\\Catalogue')->regress(0, 'tests');
        $this->migrations->setNamespace('MCMS\\Modules\\ProductImages')->regress(0, 'tests');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    private function fixture(): string
    {
        $file = $this->directory . '/source.png';
        $image = imagecreatetruecolor(600, 400);
        imagefilledrectangle($image, 0, 0, 299, 399, imagecolorallocate($image, 255, 0, 0));
        imagefilledrectangle($image, 300, 0, 599, 399, imagecolorallocate($image, 0, 0, 255));
        imagepng($image, $file);
        imagedestroy($image);
        return $file;
    }

    private function data(string $set, string $slug = 'coffee'): array
    {
        return ['title' => 'Coffee', 'slug' => $slug, 'category' => 'Drinks', 'summary' => 'Roasted beans',
            'body' => '<p>Arabica</p><script>alert(1)</script>', 'status' => 'published', 'image_set_id' => $set,
            'variants' => [['sku' => strtoupper($slug) . '-250', 'label' => '250 g', 'price' => '1250,05'],
                ['sku' => strtoupper($slug) . '-500', 'label' => '500 g', 'price' => '2400']]];
    }

    public function testModuleBoundaryAndDisablePreserveData(): void
    {
        self::assertTrue($this->services->has('product-images.library'));
        self::assertTrue($this->services->has('catalogue.repository'));
        self::assertTrue($this->navigation->has('catalogue.product'));
        self::assertFalse($this->db->tableExists('media_files'));
        self::assertFalse($this->db->tableExists('galleries'));
        $product = $this->catalogue->save(null, $this->data($this->images->createSet(1)), 1);
        self::assertSame('/shop/coffee', $this->navigation->resolve('catalogue.product', (string) $product['id'])['url']);
        self::assertSame(1, $this->services->get('catalogue.search')->countSearch('Coffee'));
        try { $this->manager->disable('product-images'); self::fail('Dependency disabled'); }
        catch (RuntimeException) { self::assertTrue($this->services->has('product-images.library')); }
        $this->manager->disable('catalogue');
        $this->manager->disable('product-images');
        self::assertSame(1, $this->db->table('catalogue_products')->countAllResults());
        $this->manager->enable('catalogue');
        self::assertSame($product['id'], $this->catalogue->find($product['id'])['id']);
    }

    public function testOriginalCropOrderingReplacementAndDeletion(): void
    {
        $set = $this->images->createSet(1);
        $source = $this->fixture();
        $items = $this->images->upload($set, $source, 'one.png');
        $first = $items[0]['id'];
        $items = $this->images->upload($set, $source, 'two.png');
        $second = $items[1]['id'];
        $original = $this->db->table('product_images')->where('id', $first)->get()->getRowArray()['original'];
        $originalPath = $this->directory . '/' . $set . '/' . $original;
        self::assertSame(hash_file('sha256', $source), hash_file('sha256', $originalPath));
        $items = $this->images->crop($set, $first, ['x' => 300, 'y' => 0, 'width' => 300, 'height' => 400, 'size' => 300]);
        $derived = $this->images->file($set, $first, 'full');
        self::assertSame([225, 300], array_slice(getimagesize($derived['path']), 0, 2));
        $image = imagecreatefromwebp($derived['path']);
        $color = imagecolorsforindex($image, imagecolorat($image, 100, 100));
        self::assertGreaterThan(240, $color['blue']);
        self::assertLessThan(10, $color['red']);
        imagedestroy($image);
        self::assertSame(hash_file('sha256', $source), hash_file('sha256', $originalPath));
        $this->images->crop($set, $first, ['x' => 0, 'y' => 0, 'width' => 600, 'height' => 400, 'size' => 1600]);
        self::assertSame([600, 400], array_slice(getimagesize($this->images->file($set, $first, 'full')['path']), 0, 2));
        self::assertSame(hash_file('sha256', $source), hash_file('sha256', $originalPath));
        $this->images->arrange($set, [$second, $first], $second);
        $product = $this->catalogue->save(null, $this->data($set), 1);
        $this->db->table('product_image_sets')->where('id', $set)->update(['updated_at' => '2099-01-01 00:00:00']);
        $reopened = $this->catalogue->find($product['id']);
        self::assertSame('2099-01-01 00:00:00', $reopened['updated_at']);
        self::assertSame([$second, $first], array_column($reopened['images'], 'id'));
        self::assertSame($second, $reopened['main_image']['id']);
        self::assertSame('/shop/coffee/images/' . $second . '/full?v=' . $reopened['main_image']['revision'], $reopened['main_image']['full_url']);
        $replaced = $this->images->upload($set, $source, 'replacement.png', $second);
        self::assertSame([$second, $first], array_column($replaced, 'id'));
        self::assertTrue($replaced[0]['main']);
        self::assertSame('replacement.png', $replaced[0]['name']);
        $this->images->remove($set, $second);
        self::assertSame($first, $this->catalogue->find($product['id'])['main_image']['id']);
        $this->catalogue->delete($product['id']);
        self::assertNull($this->catalogue->find($product['id']));
        self::assertFileDoesNotExist($originalPath);
        self::assertDirectoryDoesNotExist($this->directory . '/' . $set);
        self::assertSame(0, $this->db->table('product_images')->countAllResults());
    }

    public function testExifOrientationAndUploadLimits(): void
    {
        $set = $this->images->createSet(1);
        $file = $this->directory . '/oriented.jpg';
        $image = imagecreatetruecolor(100, 200);
        imagefilledrectangle($image, 0, 0, 99, 199, imagecolorallocate($image, 0, 220, 0));
        imagejpeg($image, $file, 90);
        imagedestroy($image);
        $jpeg = file_get_contents($file);
        $exif = "Exif\0\0II" . pack('vVv', 42, 8, 1) . pack('vvVv', 0x112, 3, 1, 6) . "\0\0" . pack('V', 0);
        file_put_contents($file, substr($jpeg, 0, 2) . "\xff\xe1" . pack('n', strlen($exif) + 2) . $exif . substr($jpeg, 2));
        $items = $this->images->upload($set, $file, 'oriented.jpg');
        self::assertSame([200, 100], [$items[0]['width'], $items[0]['height']]);
        self::assertSame([200, 100], array_slice(getimagesize($this->images->file($set, $items[0]['id'], 'full')['path']), 0, 2));
        $original = $this->db->table('product_images')->where('id', $items[0]['id'])->get()->getRowArray()['original'];
        self::assertSame(hash_file('sha256', $file), hash_file('sha256', $this->directory . '/' . $set . '/' . $original));
        $large = $this->directory . '/large.png';
        copy($this->fixture(), $large);
        $stream = fopen($large, 'ab'); ftruncate($stream, 10 * 1024 * 1024 + 1); fclose($stream);
        try { $this->images->upload($set, $large, 'large.png'); self::fail('Oversized file accepted'); }
        catch (InvalidArgumentException) { self::assertCount(1, $this->images->images($set)); }
        $image = imagecreatetruecolor(4000, 3100); imagepng($image, $large); imagedestroy($image);
        clearstatcache(true, $large);
        try { $this->images->upload($set, $large, 'pixels.png'); self::fail('Oversized dimensions accepted'); }
        catch (InvalidArgumentException) { self::assertCount(1, $this->images->images($set)); }
        $source = $this->fixture();
        for ($i = 1; $i < 20; $i++) $this->images->upload($set, $source, 'photo.png');
        try { $this->images->upload($set, $source, 'extra.png'); self::fail('Image limit exceeded'); }
        catch (InvalidArgumentException) { self::assertCount(20, $this->images->images($set)); }
    }

    public function testInvalidActionsLeaveImagesAndOriginalsIntact(): void
    {
        $set = $this->images->createSet(1);
        $source = $this->fixture();
        $items = $this->images->upload($set, $source, 'photo.png');
        $id = $items[0]['id'];
        foreach ([fn () => $this->images->crop($set, $id, ['x' => -1, 'y' => 0, 'width' => 600, 'height' => 400, 'size' => 1600]),
            fn () => $this->images->crop($set, $id, ['x' => 0, 'y' => 0, 'width' => 601, 'height' => 400, 'size' => 1600]),
            fn () => $this->images->crop($set, $id, ['x' => 0, 'y' => 0, 'width' => 600, 'height' => 400, 'size' => 99999]),
            fn () => $this->images->arrange($set, [$id, $id], $id),
            fn () => $this->images->arrange($set, [], 0),
            fn () => $this->images->arrange($set, [$id], $id + 1),
            fn () => $this->images->remove($this->images->createSet(1), $id),
            fn () => $this->images->authorize($set, 2)] as $action) {
            try {
                $action();
                self::fail('Expected rejection');
            } catch (InvalidArgumentException|RuntimeException) {
                self::assertSame($items, $this->images->images($set));
            }
        }
        $bad = $this->directory . '/bad.svg';
        file_put_contents($bad, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        try {
            $this->images->upload($set, $bad, 'bad.svg'); self::fail('SVG must be rejected');
        } catch (InvalidArgumentException) {
            self::assertCount(1, $this->images->images($set));
        }
        self::assertNull($this->images->file($set, $id, '../original'));
    }

    public function testPriceAndVariantIdentityAndOwnership(): void
    {
        self::assertSame(125005, Catalogue::priceMinor('1250,05'));
        self::assertSame(10, Catalogue::priceMinor('0.1'));
        self::assertSame(0, Catalogue::priceMinor('0'));
        foreach (['1.001', '-1', '1e3', 'NaN', '9999999999'] as $input) {
            try { Catalogue::priceMinor($input); self::fail('Invalid price accepted'); }
            catch (InvalidArgumentException) { self::assertTrue(true); }
        }
        $set = $this->images->createSet(1);
        $data = $this->data($set);
        $product = $this->catalogue->save(null, $data, 1);
        self::assertStringNotContainsString('<script', $product['body']);
        self::assertSame(125005, $product['variants'][0]['price_minor']);
        self::assertSame('RUB', $product['variants'][0]['currency']);
        $data['variants'] = $product['variants'];
        $data['variants'][0]['price'] = '1300.10';
        $saved = $this->catalogue->save($product['id'], $data, 2);
        self::assertSame(array_column($product['variants'], 'id'), array_column($saved['variants'], 'id'));
        self::assertSame(130010, $saved['variants'][0]['price_minor']);
        $data['status'] = 'draft';
        $this->catalogue->save($product['id'], $data, 1);
        self::assertNull($this->catalogue->findPublishedBySlug('coffee'));
        self::assertNull($this->navigation->resolve('catalogue.product', (string) $product['id']));
        self::assertSame(0, $this->catalogue->count('', 'published'));
        try { $this->catalogue->save(null, $this->data($set, 'another'), 1); self::fail('Set reused'); }
        catch (InvalidArgumentException) { self::assertSame(1, $this->catalogue->count()); }
        $newSet = $this->images->createSet(2);
        try { $this->catalogue->save(null, $this->data($newSet, 'third'), 1); self::fail('Another user set attached'); }
        catch (RuntimeException) { self::assertSame(1, $this->catalogue->count()); }
        $badData = $this->data($this->images->createSet(1), 'duplicate');
        $badData['variants'][0]['sku'] = $saved['variants'][0]['sku'];
        try { $this->catalogue->save(null, $badData, 1); self::fail('SKU reused'); }
        catch (InvalidArgumentException) { self::assertSame(1, $this->catalogue->count()); }
        $this->images->authorize($badData['image_set_id'], 1);
        $this->db->table('product_image_sets')->where('id', $newSet)->update(['created_at' => date('Y-m-d H:i:s', time() - 90000), 'updated_at' => date('Y-m-d H:i:s', time() - 90000)]);
        $activeSet = $this->images->createSet(2);
        $this->db->table('product_image_sets')->where('id', $activeSet)->update(['created_at' => date('Y-m-d H:i:s', time() - 90000)]);
        $this->images->createSet(1);
        self::assertNull($this->db->table('product_image_sets')->where('id', $newSet)->get()->getRowArray());
        self::assertNotNull($this->db->table('product_image_sets')->where('id', $activeSet)->get()->getRowArray());
    }
}
