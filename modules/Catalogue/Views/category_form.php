<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= view('MCMS\\Modules\\Catalogue\\Views\\category_assets') ?>
<?php
$type = old('icon_type', $category['icon_type'] ?? 'tabler');
$icon = old('icon', $category['icon'] ?? 'folder');
$color = old('icon_color', $category['icon_color'] ?? '#206bc4');
$parent = old('parent_id', $category['parent_id'] ?? service('request')->getGet('parent') ?? '');
// Old input is untrusted, including inline CSS and class names.
$icon = in_array($icon, \MCMS\Modules\Catalogue\Services\Categories::icons(), true) ? $icon : 'folder';
$color = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color) === 1 ? $color : '#206bc4';
?>
<div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-4"><div><div class="page-pretitle"><?= esc(lang('Catalogue.categories')) ?></div><h1 class="page-title"><?= esc($title) ?></h1></div><a class="btn" href="<?= site_url('admin/catalogue/categories') ?>"><?= esc(lang('Catalogue.manageCategories')) ?></a></div>
<form method="post" enctype="multipart/form-data" data-category-form>
    <?= csrf_field() ?>
    <div class="row g-3"><div class="col-lg-7"><div class="card"><div class="card-body">
        <label class="form-label" for="category-name"><?= esc(lang('Catalogue.title')) ?></label><input class="form-control mb-3" id="category-name" name="name" required maxlength="120" value="<?= esc(old('name', $category['name'] ?? ''), 'attr') ?>">
        <label class="form-label" for="category-slug"><?= esc(lang('Catalogue.slug')) ?> (slug)</label><div class="input-group mb-3"><span class="input-group-text">/shop/category/</span><input class="form-control" id="category-slug" name="slug" required maxlength="190" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?= esc(old('slug', $category['slug'] ?? ''), 'attr') ?>"></div>
        <label class="form-label" for="category-parent"><?= esc(lang('Catalogue.parentCategory')) ?></label><select class="form-select mb-3" id="category-parent" name="parent_id"><option value=""><?= esc(lang('Catalogue.rootCategory')) ?></option><?php foreach ($parents as $item): ?><option value="<?= $item['id'] ?>" <?= (string) $parent === (string) $item['id'] ? 'selected' : '' ?>><?= esc($item['path']) ?></option><?php endforeach ?></select>
        <p class="text-muted"><?= esc(lang('Catalogue.categoryTreeHint')) ?></p>
    </div></div></div><div class="col-lg-5"><div class="card"><div class="card-body">
        <label class="form-label" for="category-icon-type"><?= esc(lang('Catalogue.categoryAppearance')) ?></label><select class="form-select mb-3" id="category-icon-type" name="icon_type"><option value="tabler" <?= $type === 'tabler' ? 'selected' : '' ?>><?= esc(lang('Catalogue.tablerIcon')) ?></option><option value="image" <?= $type === 'image' ? 'selected' : '' ?>><?= esc(lang('Catalogue.uploadedIcon')) ?></option></select>
        <div data-tabler-fields <?= $type === 'image' ? 'hidden' : '' ?>>
            <input type="hidden" name="icon" value="<?= esc($icon, 'attr') ?>">
            <button class="btn w-100 mb-3 d-flex gap-3 align-items-center justify-content-center" type="button" data-open-icon-picker aria-haspopup="dialog" aria-controls="category-icon-dialog"><i class="ti ti-<?= esc($icon, 'attr') ?> mcms-category-icon" data-icon-preview style="color:<?= esc($color, 'attr') ?>" aria-hidden="true"></i><?= esc(lang('Catalogue.chooseIcon')) ?></button>
            <label class="form-label" for="category-icon-color"><?= esc(lang('Catalogue.iconColor')) ?></label><input class="form-control form-control-color mb-3" id="category-icon-color" type="color" name="icon_color" value="<?= esc($color, 'attr') ?>">
        </div>
        <div data-image-fields <?= $type === 'image' ? '' : 'hidden' ?>>
            <label class="form-label" for="category-image"><?= esc(lang('Catalogue.uploadedIcon')) ?></label><input class="form-control mb-2" type="file" id="category-image" name="image" accept="image/jpeg,image/png,image/webp" <?= $type === 'image' && ($category['image_url'] ?? null) === null ? 'required' : '' ?>>
            <p class="text-muted small"><?= esc(lang('Catalogue.categoryImageHint')) ?></p>
            <img class="mcms-category-image-preview mb-3" data-image-preview src="<?= ($category['image_url'] ?? null) === null ? '' : esc(site_url(ltrim($category['image_url'], '/')), 'attr') ?>" alt="<?= esc(lang('Catalogue.iconPreview'), 'attr') ?>" <?= ($category['image_url'] ?? null) === null ? 'hidden' : '' ?> data-existing="<?= ($category['image_url'] ?? null) === null ? '0' : '1' ?>">
        </div>
        <button class="btn btn-primary w-100" type="submit"><?= esc(lang('Catalogue.saveCategory')) ?></button>
    </div></div></div></div>
</form>
<dialog class="mcms-category-dialog mcms-icon-picker" id="category-icon-dialog" aria-labelledby="icon-picker-title" data-icon-picker data-source="<?= site_url('shop/assets/tabler-icons.json') ?>">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><h2 class="h3 mb-0" id="icon-picker-title"><?= esc(lang('Catalogue.chooseIcon')) ?></h2><button class="btn" type="button" data-close-dialog><?= esc(lang('Catalogue.close')) ?></button></div>
    <label class="form-label" for="icon-search"><?= esc(lang('Catalogue.searchIcons')) ?></label><input class="form-control mb-3" type="search" id="icon-search" placeholder="<?= esc(lang('Catalogue.iconSearchHint'), 'attr') ?>" data-icon-search>
    <p class="text-muted" role="status" data-icon-status data-loading="<?= esc(lang('Catalogue.loadingIcons'), 'attr') ?>" data-error="<?= esc(lang('Catalogue.iconLoadFailed'), 'attr') ?>" data-empty="<?= esc(lang('Catalogue.noIcons'), 'attr') ?>"></p>
    <div class="mcms-icon-grid" data-icon-grid></div>
    <div class="d-flex gap-3 align-items-center justify-content-between mt-3"><button class="btn" type="button" data-icon-prev><?= esc(lang('Catalogue.previous')) ?></button><span data-icon-page></span><button class="btn" type="button" data-icon-next><?= esc(lang('Catalogue.next')) ?></button></div>
</dialog>
<?= $this->endSection() ?>
