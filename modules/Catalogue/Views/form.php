<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$variants = old('variants', $product['variants'] ?? [['id' => 0, 'sku' => '', 'label' => '', 'price' => '']]);
$variants = is_array($variants) ? array_slice(array_values(array_filter($variants, 'is_array')), 0, 50) : [];
$variants = $variants ?: [['id' => 0, 'sku' => '', 'label' => '', 'price' => '']];
$status = old('status', $product['status'] ?? 'draft');
?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4"><div><div class="page-pretitle"><?= esc(lang('Catalogue.name')) ?></div><h1 class="page-title"><?= esc($product['title'] ?? lang('Catalogue.new')) ?></h1></div><a class="btn" href="<?= site_url('admin/catalogue') ?>"><?= esc(lang('Catalogue.back')) ?></a></div>
<form method="post" id="product-form">
    <?= csrf_field() ?><input type="hidden" name="image_set_id" value="<?= esc($setId, 'attr') ?>">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-4"><div class="card-body">
                <label class="form-label" for="product-title"><?= esc(lang('Catalogue.title')) ?></label>
                <input class="form-control mb-3" id="product-title" name="title" required maxlength="255" value="<?= esc(old('title', $product['title'] ?? ''), 'attr') ?>">
                <label class="form-label" for="product-slug"><?= esc(lang('Catalogue.slug')) ?></label>
                <div class="input-group mb-3"><span class="input-group-text">/shop/</span><input class="form-control" id="product-slug" name="slug" required maxlength="190" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?= esc(old('slug', $product['slug'] ?? ''), 'attr') ?>"></div>
                <label class="form-label" for="product-summary"><?= esc(lang('Catalogue.summary')) ?></label>
                <textarea class="form-control mb-3" id="product-summary" name="summary" rows="3" maxlength="2000"><?= esc(old('summary', $product['summary'] ?? '')) ?></textarea>
                <label class="form-label" for="product-body"><?= esc(lang('Catalogue.body')) ?></label>
                <textarea class="form-control" id="product-body" name="body" rows="8" maxlength="100000"><?= esc(old('body', $product['body'] ?? '')) ?></textarea>
                <small class="text-muted"><?= esc(lang('Catalogue.htmlHint')) ?></small>
            </div></div>
            <?php if ($canImages): ?><?= view('MCMS\\Modules\\ProductImages\\Views\\editor', ['setId' => $setId, 'items' => $items]) ?><?php else: ?><div class="card mb-4"><div class="card-body text-muted"><?= esc(lang('Catalogue.imageDenied')) ?></div></div><?php endif ?>
            <div class="card mb-4"><div class="card-header"><h2 class="card-title"><?= esc(lang('Catalogue.variants')) ?></h2></div><div class="card-body">
                <p class="text-muted"><?= esc(lang('Catalogue.variantHint')) ?></p>
                <div data-variants>
                <?php foreach ($variants as $index => $variant): ?>
                    <div class="row g-2 align-items-end mb-3" data-variant>
                        <input type="hidden" name="variants[<?= $index ?>][id]" value="<?= (int) ($variant['id'] ?? 0) ?>">
                        <div class="col-12 col-sm-4"><label class="form-label" for="variant-<?= $index ?>-sku"><?= esc(lang('Catalogue.sku')) ?></label><input class="form-control" id="variant-<?= $index ?>-sku" name="variants[<?= $index ?>][sku]" value="<?= esc($variant['sku'] ?? '', 'attr') ?>" maxlength="100" required></div>
                        <div class="col-6 col-sm-4"><label class="form-label" for="variant-<?= $index ?>-label"><?= esc(lang('Catalogue.variant')) ?></label><input class="form-control" id="variant-<?= $index ?>-label" name="variants[<?= $index ?>][label]" value="<?= esc($variant['label'] ?? '', 'attr') ?>" maxlength="120"></div>
                        <div class="col-6 col-sm-3"><label class="form-label" for="variant-<?= $index ?>-price"><?= esc(lang('Catalogue.price')) ?></label><input class="form-control" id="variant-<?= $index ?>-price" name="variants[<?= $index ?>][price]" value="<?= esc($variant['price'] ?? '', 'attr') ?>" inputmode="decimal" pattern="[0-9]+([.,][0-9]{1,2})?" required></div>
                        <div class="col-12 col-sm-1"><button class="btn btn-outline-danger w-100" type="button" data-remove-variant aria-label="<?= esc(lang('Catalogue.removeVariant'), 'attr') ?>">×</button></div>
                    </div>
                <?php endforeach ?>
                </div>
                <button type="button" class="btn" data-add-variant><?= esc(lang('Catalogue.addVariant')) ?></button>
            </div></div>
        </div>
        <div class="col-lg-4"><div class="card"><div class="card-body">
            <label class="form-label" for="product-status"><?= esc(lang('Catalogue.status')) ?></label>
            <select class="form-select mb-3" id="product-status" name="status"><?php foreach (['draft', 'published'] as $value): ?><option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= esc(lang('Catalogue.' . $value)) ?></option><?php endforeach ?></select>
            <label class="form-label" for="product-category"><?= esc(lang('Catalogue.category')) ?></label>
            <select class="form-select mb-2" id="product-category" name="category_id"><option value=""><?= esc(lang('Catalogue.noCategory')) ?></option><?php foreach ($categories as $item): ?><option value="<?= $item['id'] ?>" <?= (string) old('category_id', $product['category_id'] ?? '') === (string) $item['id'] ? 'selected' : '' ?>><?= esc($item['path']) ?></option><?php endforeach ?></select>
            <a class="d-block mb-3" href="<?= site_url('admin/catalogue/categories') ?>"><?= esc(lang('Catalogue.manageCategories')) ?></a>
            <button class="btn btn-primary w-100" type="submit" data-product-save><?= esc(lang('Catalogue.save')) ?></button>
            <?php if ($product !== null && $product['status'] === 'published'): ?><a href="<?= site_url(ltrim($product['url'], '/')) ?>" class="btn w-100 mt-2" target="_blank" rel="noopener"><?= esc(lang('Catalogue.open')) ?></a><?php endif ?>
        </div></div></div>
    </div>
</form>
<script>
(() => {
    const form = document.querySelector('#product-form');
    const variants = form.querySelector('[data-variants]');
    const template = variants.querySelector('[data-variant]').cloneNode(true);
    let next = variants.children.length;
    function refresh() { variants.querySelectorAll('[data-remove-variant]').forEach(button => { button.disabled = variants.children.length === 1; }); }
    form.querySelector('[data-add-variant]').addEventListener('click', () => {
        if (variants.children.length >= 50) return;
        const row = template.cloneNode(true);
        row.querySelectorAll('input').forEach(input => {
            input.name = input.name.replace(/variants\[\d+\]/, `variants[${next}]`);
            if (input.id) input.id = input.id.replace(/variant-\d+-/, `variant-${next}-`);
            input.value = input.type === 'hidden' ? '0' : '';
        });
        row.querySelectorAll('label').forEach(label => { label.htmlFor = label.htmlFor.replace(/variant-\d+-/, `variant-${next}-`); });
        variants.append(row); next++; refresh(); row.querySelector('input:not([type=hidden])').focus();
    });
    variants.addEventListener('click', event => {
        if (!event.target.closest('[data-remove-variant]') || variants.children.length === 1) return;
        event.target.closest('[data-variant]').remove(); refresh();
    });
    const title = form.querySelector('#product-title'), slug = form.querySelector('#product-slug');
    let automatic = !slug.value;
    const cyrillic = {а:'a',б:'b',в:'v',г:'g',д:'d',е:'e',ё:'yo',ж:'zh',з:'z',и:'i',й:'y',к:'k',л:'l',м:'m',н:'n',о:'o',п:'p',р:'r',с:'s',т:'t',у:'u',ф:'f',х:'h',ц:'c',ч:'ch',ш:'sh',щ:'sch',ъ:'',ы:'y',ь:'',э:'e',ю:'yu',я:'ya'};
    title.addEventListener('input', () => { if (automatic) slug.value = [...title.value.toLowerCase()].map(char => cyrillic[char] ?? char).join('').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0,190).replace(/-$/,''); });
    slug.addEventListener('input', () => { automatic = !slug.value; });
    refresh();
})();
</script>
<?= $this->endSection() ?>
