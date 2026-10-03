<?= view('MCMS\\Modules\\Catalogue\\Views\\public_assets') ?>
<article class="mcms-shop" data-product-gallery>
    <a class="mcms-shop-back" href="<?= site_url('shop') ?>">← <?= esc(lang('Catalogue.back')) ?></a>
    <div class="mcms-product-detail">
        <div class="mcms-product-photos">
            <?php if ($product['main_image'] !== null): ?>
                <a class="mcms-product-hero" data-photo-open href="<?= esc(site_url(ltrim($product['main_image']['full_url'], '/')), 'attr') ?>" aria-label="<?= esc(lang('Catalogue.enlarge'), 'attr') ?>"><img data-photo-hero src="<?= esc(site_url(ltrim($product['main_image']['display_url'], '/')), 'attr') ?>" alt="<?= esc($product['title'], 'attr') ?>"><span>＋ <?= esc(lang('Catalogue.enlarge')) ?></span></a>
                <div class="mcms-product-thumbnails" role="group" aria-label="<?= esc(lang('ProductImages.title'), 'attr') ?>">
                    <?php foreach ($product['images'] as $index => $image): ?><a href="<?= esc(site_url(ltrim($image['full_url'], '/')), 'attr') ?>" data-photo-thumb data-display="<?= esc(site_url(ltrim($image['display_url'], '/')), 'attr') ?>" aria-label="<?= esc($product['title'] . ' — ' . ($index + 1), 'attr') ?>" aria-current="<?= $image['main'] ? 'true' : 'false' ?>"><img src="<?= esc(site_url(ltrim($image['thumb_url'], '/')), 'attr') ?>" alt="" loading="lazy"></a><?php endforeach ?>
                </div>
            <?php else: ?><div class="mcms-product-hero mcms-product-placeholder"><?= esc(lang('Catalogue.noPhoto')) ?></div><?php endif ?>
        </div>
        <div class="mcms-product-info"><p class="mcms-shop-eyebrow"><?= esc($product['category']) ?></p><h1><?= esc($product['title']) ?></h1><p class="mcms-product-summary"><?= nl2br(esc($product['summary'])) ?></p>
            <?php if ($product['variants'] !== []): $variant = $product['variants'][0]; ?>
                <p class="mcms-product-price" data-variant-price><?= esc($variant['price_display']) ?></p><p class="mcms-shop-sku"><?= esc(lang('Catalogue.sku')) ?>: <span data-variant-sku><?= esc($variant['sku']) ?></span></p>
                <?php if (count($product['variants']) > 1): ?><label for="shop-variant" class="form-label"><?= esc(lang('Catalogue.chooseVariant')) ?></label><select id="shop-variant" class="form-select" data-variant-select><?php foreach ($product['variants'] as $option): ?><option value="<?= $option['id'] ?>" data-price="<?= esc($option['price_display'], 'attr') ?>" data-sku="<?= esc($option['sku'], 'attr') ?>"><?= esc($option['label'] ?: $option['sku']) ?></option><?php endforeach ?></select><?php endif ?>
            <?php endif ?>
            <?php if ($product['body'] !== ''): ?><div class="mcms-product-description"><h2><?= esc(lang('Catalogue.details')) ?></h2><div class="mcms-page-content"><?= $product['body'] ?></div></div><?php endif ?>
        </div>
    </div>
    <dialog class="mcms-product-lightbox" data-photo-dialog aria-label="<?= esc($product['title'], 'attr') ?>"><button type="button" data-photo-close class="mcms-photo-close" aria-label="<?= esc(lang('Catalogue.close'), 'attr') ?>">×</button><img data-photo-full alt="<?= esc($product['title'], 'attr') ?>"><div class="mcms-photo-controls"><button type="button" data-photo-prev aria-label="<?= esc(lang('Catalogue.previous'), 'attr') ?>">←</button><span data-photo-counter></span><button type="button" data-photo-next aria-label="<?= esc(lang('Catalogue.next'), 'attr') ?>">→</button></div></dialog>
</article>
