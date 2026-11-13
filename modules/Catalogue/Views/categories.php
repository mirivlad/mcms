<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= view('MCMS\\Modules\\Catalogue\\Views\\category_assets') ?>
<div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-4">
    <h1 class="page-title"><?= esc(lang('Catalogue.categories')) ?></h1>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn" href="<?= site_url('admin/catalogue') ?>"><?= esc(lang('Catalogue.backStore')) ?></a>
        <?php if ($canCreate): ?><a class="btn btn-primary" href="<?= site_url('admin/catalogue/categories/create') ?>"><?= esc(lang('Catalogue.createCategory')) ?></a><?php endif ?>
    </div>
</div>
<?php if (session()->getFlashdata('message')): ?><div class="alert alert-success"><?= esc(session()->getFlashdata('message')) ?></div><?php endif ?>
<?php if (session()->getFlashdata('error')): ?><div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>
<p class="text-muted"><?= esc($canEdit ? lang('Catalogue.categoryDragHint') : lang('Catalogue.categoryTreeHint')) ?></p>
<?php
$groups = [];
foreach ($categories as $category) {
    $groups[$category['parent_id'] ?? 0][] = $category;
}
$nest = function (int $parent) use (&$nest, $groups): array {
    $result = [];
    foreach ($groups[$parent] ?? [] as $category) {
        $category['children'] = $nest($category['id']);
        $result[] = $category;
    }
    return $result;
};
$tree = $nest(0);
?>
<?php if ($categories === []): ?>
    <div class="card"><div class="card-body text-muted"><?= esc(lang('Catalogue.noCategories')) ?></div></div>
<?php else: ?>
    <?php if ($canEdit): ?>
    <form method="post" action="<?= site_url('admin/catalogue/categories/order') ?>" data-category-order-form>
        <?= csrf_field() ?>
        <input type="hidden" name="structure" data-category-structure>
    <?php endif ?>
    <div class="card"><div class="card-body" data-category-sort-container>
        <?php if ($canEdit): ?><div class="mcms-category-root-drop" data-category-root-zone="start"><?= esc(lang('Catalogue.categoryRootStart')) ?></div><?php endif ?>
        <?= view('MCMS\\Modules\\Catalogue\\Views\\category_tree_nodes', [
            'nodes' => $tree, 'canEdit' => $canEdit, 'canCreate' => $canCreate, 'canDelete' => $canDelete,
            'isRoot' => true,
        ]) ?>
        <?php if ($canEdit): ?><div class="mcms-category-root-drop" data-category-root-zone="end"><?= esc(lang('Catalogue.categoryRootEnd')) ?></div><?php endif ?>
    </div>
    <?php if ($canEdit): ?><div class="card-footer"><button class="btn btn-primary" type="submit" data-category-save-order disabled><?= esc(lang('Catalogue.saveCategoryOrder')) ?></button></div><?php endif ?>
    </div>
    <?php if ($canEdit): ?></form><?php endif ?>
<?php endif ?>
<?php if ($canDelete): ?>
<dialog class="mcms-category-dialog" data-delete-category-dialog aria-labelledby="delete-category-title">
    <h2 class="h3" id="delete-category-title"></h2>
    <p><?= esc(lang('Catalogue.confirmCategoryDelete')) ?></p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="confirm" value="1">
        <div class="d-flex justify-content-end gap-2">
            <button class="btn" type="button" data-close-dialog><?= esc(lang('Catalogue.cancel')) ?></button>
            <button class="btn btn-danger" type="submit"><?= esc(lang('Catalogue.deleteCategory')) ?></button>
        </div>
    </form>
</dialog>
<?php endif ?>
<?= $this->endSection() ?>
