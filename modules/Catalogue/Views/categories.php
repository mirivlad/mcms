<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= view('MCMS\\Modules\\Catalogue\\Views\\category_assets') ?>
<div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-4"><h1 class="page-title"><?= esc(lang('Catalogue.categories')) ?></h1><div class="d-flex gap-2 flex-wrap"><a class="btn" href="<?= site_url('admin/catalogue') ?>"><?= esc(lang('Catalogue.backStore')) ?></a><?php if ($canCreate): ?><a class="btn btn-primary" href="<?= site_url('admin/catalogue/categories/create') ?>"><?= esc(lang('Catalogue.createCategory')) ?></a><?php endif ?></div></div>
<p class="text-muted"><?= esc(lang('Catalogue.categoryTreeHint')) ?></p>
<div class="card"><div class="list-group list-group-flush">
<?php foreach ($categories as $category): ?>
    <div class="list-group-item d-flex align-items-center gap-3 flex-wrap">
        <div class="mcms-category-entry flex-fill" style="padding-inline-start:<?= min(6, $category['depth']) ?>rem">
            <?= view('MCMS\\Modules\\Catalogue\\Views\\category_icon', ['category' => $category]) ?>
            <div><strong><?= esc($category['name']) ?></strong><div class="text-muted small"><?= esc($category['path']) ?></div><a class="small" href="<?= site_url(ltrim($category['url'], '/')) ?>" target="_blank" rel="noopener"><?= esc($category['url']) ?></a></div>
        </div>
        <?php if ($canCreate): ?><a class="btn btn-sm btn-icon admin-icon-action" href="<?= site_url('admin/catalogue/categories/create') ?>?parent=<?= $category['id'] ?>" aria-label="<?= esc(lang('Catalogue.addSubcategory'), 'attr') ?>" title="<?= esc(lang('Catalogue.addSubcategory'), 'attr') ?>" data-bs-toggle="tooltip" data-bs-container="body"><i class="ti ti-folder-plus" aria-hidden="true"></i></a><?php endif ?>
        <?php if ($canEdit): ?><a class="btn btn-sm btn-icon admin-icon-action" href="<?= site_url('admin/catalogue/categories/' . $category['id'] . '/edit') ?>" aria-label="<?= esc(lang('Catalogue.editCategory'), 'attr') ?>" title="<?= esc(lang('Catalogue.editCategory'), 'attr') ?>" data-bs-toggle="tooltip" data-bs-container="body"><i class="ti ti-pencil" aria-hidden="true"></i></a><?php endif ?>
        <?php if ($canDelete): ?><button class="btn btn-sm btn-outline-danger btn-icon admin-icon-action" type="button" data-delete-category="<?= site_url('admin/catalogue/categories/' . $category['id'] . '/delete') ?>" data-title="<?= esc($category['name'], 'attr') ?>" aria-label="<?= esc(lang('Catalogue.deleteCategory'), 'attr') ?>" title="<?= esc(lang('Catalogue.deleteCategory'), 'attr') ?>" data-bs-toggle="tooltip" data-bs-container="body"><i class="ti ti-trash" aria-hidden="true"></i></button><?php endif ?>
    </div>
<?php endforeach ?>
<?php if ($categories === []): ?><div class="card-body text-muted"><?= esc(lang('Catalogue.noCategories')) ?></div><?php endif ?>
</div></div>
<?php if ($canDelete): ?>
<dialog class="mcms-category-dialog" data-delete-category-dialog aria-labelledby="delete-category-title"><h2 class="h3" id="delete-category-title"></h2><p><?= esc(lang('Catalogue.confirmCategoryDelete')) ?></p><form method="post"><?= csrf_field() ?><input type="hidden" name="confirm" value="1"><div class="d-flex justify-content-end gap-2"><button class="btn" type="button" data-close-dialog><?= esc(lang('Catalogue.cancel')) ?></button><button class="btn btn-danger" type="submit"><?= esc(lang('Catalogue.deleteCategory')) ?></button></div></form></dialog>
<?php endif ?>
<?= $this->endSection() ?>
