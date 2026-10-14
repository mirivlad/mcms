<?= view('MCMS\\Modules\\Catalogue\\Views\\public_assets') ?>
<?php if ($cartEnabled ?? false): ?><?= view('MCMS\\Modules\\Cart\\Views\\public_assets') ?><?php endif ?>
<section class="mcms-shop">
    <header class="mcms-shop-heading">
        <div>
            <p class="mcms-shop-eyebrow"><?= esc($siteName) ?></p>
            <h1><?= esc($selectedCategory['name'] ?? lang('Catalogue.name')) ?></h1>
        </div>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <span><?= esc(lang('Catalogue.count')) ?>: <?= $count ?></span>
            <?php if ($cartEnabled ?? false): ?>
                <?= view('MCMS\\Modules\\Cart\\Views\\link', ['count' => $cartCount, 'class' => 'mcms-cart-link mcms-cart-shop-link']) ?>
            <?php endif ?>
        </div>
    </header>
    <form class="mcms-shop-filter" method="get" action="<?= site_url('shop') ?>"><input class="form-control" name="q" value="<?= esc($query, 'attr') ?>" placeholder="<?= esc(lang('Catalogue.search'), 'attr') ?>" aria-label="<?= esc(lang('Catalogue.search'), 'attr') ?>"><select class="form-select" name="category" aria-label="<?= esc(lang('Catalogue.category'), 'attr') ?>"><option value=""><?= esc(lang('Catalogue.allCategories')) ?></option><?php foreach ($categories as $item): ?><option value="<?= esc($item['slug'], 'attr') ?>" <?= $category === $item['slug'] ? 'selected' : '' ?>><?= esc($item['path']) ?></option><?php endforeach ?></select><button class="btn btn-outline-secondary" type="submit"><?= esc(lang('Catalogue.search')) ?></button></form>
    <?php if ($selectedCategory !== null): ?><a class="mcms-shop-back" href="<?= site_url('shop') ?>">← <?= esc(lang('Catalogue.allCategories')) ?></a><?php endif ?>
    <?= view('MCMS\\Modules\\Catalogue\\Views\\category_menu', ['tree' => $categoryMenuTree ?? [], 'style' => $categoryMenuStyle ?? 'tree', 'selectedSlug' => $category]) ?>
    <?= view('MCMS\\Modules\\Catalogue\\Views\\secondary_menu', ['menu' => $secondaryMenu ?? []]) ?>

    <div class="mcms-product-grid">
    <?php foreach ($products as $product): ?>
        <article class="mcms-product-card" data-cart-product-card>
            <a class="mcms-product-card-link" href="<?= esc(site_url(ltrim($product['url'], '/')), 'attr') ?>">
                <div class="mcms-product-card-image"><?php if ($product['main_image'] !== null): ?><img src="<?= esc(site_url(ltrim($product['main_image']['display_url'], '/')), 'attr') ?>" alt="<?= esc($product['title'], 'attr') ?>" loading="lazy"><?php else: ?><span><?= esc(lang('Catalogue.noPhoto')) ?></span><?php endif ?></div>
                <div class="mcms-product-card-body"><span class="mcms-shop-eyebrow"><?= esc($product['category']) ?></span><h2><?= esc($product['title']) ?></h2><p><?= esc($product['summary']) ?></p><strong class="mcms-product-price"><?= count($product['variants']) > 1 ? esc(lang('Catalogue.from')) . ' ' : '' ?><?= esc($product['from_price']) ?></strong></div>
            </a>
            <?php if ($cartEnabled ?? false): ?>
                <div class="mcms-cart-add-panel">
                    <?= view('MCMS\\Modules\\Cart\\Views\\add_form', [
                        'variants' => $product['variants'],
                        'stockLevels' => $cartStockLevels,
                        'returnPath' => $cartReturn,
                        'showVariantSelect' => true,
                    ]) ?>
                </div>
            <?php endif ?>
            <?= view('themes/content_actions', ['targetType' => 'catalogue.product', 'targetId' => (string) $product['id']]) ?>
            <?php if ($canManageStock ?? false): ?>
                <div class="mcms-stock-card-panel">
                    <?php if (count($product['variants']) > 1): ?><details><summary><?= esc(lang('Stock.title')) ?></summary><div class="mcms-stock-card-rows"><?php endif ?>
                    <?php foreach ($product['variants'] as $variant): ?>
                        <?= view('MCMS\\Modules\\Stock\\Views\\quick', [
                            'variant' => $variant,
                            'stock' => $stockLevels[$variant['id']] ?? null,
                            'returnPath' => $stockReturn,
                        ]) ?>
                    <?php endforeach ?>
                    <?php if (count($product['variants']) > 1): ?></div></details><?php endif ?>
                </div>
            <?php endif ?>
        </article>
    <?php endforeach ?>
    </div>

    <?php if ($products === []): ?><p class="py-5"><?= esc(lang($query !== '' || $category !== '' ? 'Catalogue.noResults' : 'Catalogue.empty')) ?></p><?php endif ?>
    <?php if ($lastPage > 1): ?><nav class="mcms-shop-pagination"><?php if ($page > 1): ?><a href="?<?= esc(http_build_query(['q' => $query, 'category' => $category, 'page' => $page - 1]), 'attr') ?>"><?= esc(lang('Catalogue.previous')) ?></a><?php endif ?><span><?= $page ?> / <?= $lastPage ?></span><?php if ($page < $lastPage): ?><a href="?<?= esc(http_build_query(['q' => $query, 'category' => $category, 'page' => $page + 1]), 'attr') ?>"><?= esc(lang('Catalogue.next')) ?></a><?php endif ?></nav><?php endif ?>
</section>
