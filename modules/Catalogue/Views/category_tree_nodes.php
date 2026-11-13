<?php $isRoot = $isRoot ?? false; ?>
<ul class="mcms-category-sort-list <?= $isRoot ? 'mcms-category-sort-root' : '' ?>" <?= $isRoot ? 'data-category-root' : 'data-category-children' ?>>
    <?php foreach ($nodes as $category): ?>
    <li class="mcms-category-sort-item" data-category-item data-id="<?= (int) $category['id'] ?>">
        <div class="mcms-category-sort-row">
            <?php if ($canEdit): ?>
                <span class="mcms-category-grip" data-category-handle draggable="true" tabindex="0" title="<?= esc(lang('Catalogue.categoryDragHint'), 'attr') ?>" aria-label="<?= esc(lang('Catalogue.categoryDragHint'), 'attr') ?>"><i class="ti ti-grip-vertical" aria-hidden="true"></i></span>
            <?php endif ?>
            <div class="mcms-category-entry flex-fill">
                <?= view('MCMS\\Modules\\Catalogue\\Views\\category_icon', ['category' => $category]) ?>
                <div class="min-w-0"><strong><?= esc($category['name']) ?></strong>
                    <div class="text-muted small text-break"><?= esc($category['path']) ?></div>
                    <a class="small" href="<?= site_url(ltrim($category['url'], '/')) ?>" target="_blank" rel="noopener"><?= esc($category['url']) ?></a>
                </div>
            </div>
            <div class="d-flex gap-1 flex-wrap align-items-center justify-content-end">
                <?php if ($canEdit): ?>
                    <?php foreach (['up' => ['arrow-up', 'categoryMoveUp'], 'down' => ['arrow-down', 'categoryMoveDown'], 'nest' => ['indent-increase', 'categoryNest'], 'unnest' => ['indent-decrease', 'categoryUnnest']] as $action => [$icon, $label]): ?>
                        <button class="btn btn-sm btn-icon admin-icon-action" type="button" data-category-move="<?= esc($action, 'attr') ?>" title="<?= esc(lang('Catalogue.' . $label), 'attr') ?>" aria-label="<?= esc(lang('Catalogue.' . $label), 'attr') ?>"><i class="ti ti-<?= esc($icon, 'attr') ?>" aria-hidden="true"></i></button>
                    <?php endforeach ?>
                <?php endif ?>
                <?php if ($canCreate): ?><a class="btn btn-sm btn-icon admin-icon-action" href="<?= site_url('admin/catalogue/categories/create') ?>?parent=<?= (int) $category['id'] ?>" aria-label="<?= esc(lang('Catalogue.addSubcategory'), 'attr') ?>" title="<?= esc(lang('Catalogue.addSubcategory'), 'attr') ?>"><i class="ti ti-folder-plus" aria-hidden="true"></i></a><?php endif ?>
                <?php if ($canEdit): ?><a class="btn btn-sm btn-icon admin-icon-action" href="<?= site_url('admin/catalogue/categories/' . $category['id'] . '/edit') ?>" aria-label="<?= esc(lang('Catalogue.editCategory'), 'attr') ?>" title="<?= esc(lang('Catalogue.editCategory'), 'attr') ?>"><i class="ti ti-pencil" aria-hidden="true"></i></a><?php endif ?>
                <?php if ($canDelete): ?><button class="btn btn-sm btn-outline-danger btn-icon admin-icon-action" type="button" data-delete-category="<?= site_url('admin/catalogue/categories/' . $category['id'] . '/delete') ?>" data-title="<?= esc($category['name'], 'attr') ?>" aria-label="<?= esc(lang('Catalogue.deleteCategory'), 'attr') ?>" title="<?= esc(lang('Catalogue.deleteCategory'), 'attr') ?>"><i class="ti ti-trash" aria-hidden="true"></i></button><?php endif ?>
            </div>
        </div>
        <?= view('MCMS\\Modules\\Catalogue\\Views\\category_tree_nodes', [
            'nodes' => $category['children'], 'canEdit' => $canEdit, 'canCreate' => $canCreate,
            'canDelete' => $canDelete, 'isRoot' => false,
        ]) ?>
    </li>
    <?php endforeach ?>
</ul>
