<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
    $isEdit = $menu !== null;
    $parentOptions = [];

    $collectParents = function (array $nodes, int $depth = 0) use (&$collectParents, &$parentOptions): void {
        foreach ($nodes as $node) {
            $parentOptions[] = [
                'id' => (int) $node['id'],
                'label' => str_repeat('— ', $depth) . (string) $node['label'],
            ];
            $collectParents($node['children'] ?? [], $depth + 1);
        }
    };

    $collectParents($tree);

    $itemLabel = (string) old('label', $editItem['label'] ?? '');
    $itemTarget = (string) old('target', $editItem['target'] ?? '');
    $itemParent = old('parent_id', $editItem['parent_id'] ?? '');
    $itemEnabled = old('enabled', $editItem === null || ($editItem['enabled'] ?? false) ? '1' : null) !== null;
    $itemOpenNew = old('open_in_new', ($editItem['open_in_new'] ?? false) ? '1' : null) !== null;
?>

<style>
.menu-tree-list {
    list-style: none;
    margin: .35rem 0 0 1.5rem;
    padding: 0;
}
.menu-tree-root {
    margin-left: 0;
}
.menu-tree-item {
    margin: .35rem 0;
}
.menu-tree-row {
    display: flex;
    align-items: center;
    gap: .5rem;
    min-height: 3.5rem;
    padding: .55rem .65rem;
    border: 1px solid var(--tblr-border-color);
    border-radius: var(--tblr-border-radius);
    background: var(--tblr-bg-surface);
}
.menu-tree-item.is-dragging > .menu-tree-row {
    opacity: .45;
}
.menu-tree-item.is-drop-target > .menu-tree-row {
    border-color: var(--tblr-primary);
    box-shadow: 0 0 0 1px var(--tblr-primary);
}
.menu-drag-handle {
    flex: 0 0 auto;
    color: var(--tblr-secondary);
    cursor: grab;
    touch-action: none;
}
.menu-drag-handle:active {
    cursor: grabbing;
}
@media (max-width: 767.98px) {
    .menu-tree-list {
        margin-left: .75rem;
    }
    .menu-tree-root {
        margin-left: 0;
    }
    .menu-tree-row {
        align-items: flex-start;
    }
    .menu-drag-handle {
        display: none;
    }
}
</style>

<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md">
            <div class="page-pretitle"><?= esc(lang('Menus.Admin.title')) ?></div>
            <h2 class="page-title"><?= esc(lang($isEdit ? 'Menus.Admin.edit' : 'Menus.Admin.create')) ?></h2>
        </div>
        <div class="col-12 col-md-auto ms-md-auto">
            <a class="btn btn-outline-secondary d-none d-md-inline-flex" href="<?= site_url('admin/menus') ?>">
                <i class="ti ti-arrow-left me-1"></i><?= esc(lang('MCMS.common.back')) ?>
            </a>
            <a class="d-inline-flex d-md-none align-items-center gap-1 text-secondary text-decoration-none small" href="<?= site_url('admin/menus') ?>">
                <i class="ti ti-arrow-left"></i><?= esc(lang('MCMS.common.back')) ?>
            </a>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h3 class="card-title">
            <i class="ti ti-menu-2 me-2"></i><?= esc(lang('Menus.Admin.name')) ?>
        </h3>
    </div>
    <div class="card-body">
        <form method="post">
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="menu_name"><?= esc(lang('Menus.Admin.name')) ?></label>
                    <input
                        class="form-control"
                        id="menu_name"
                        name="name"
                        maxlength="120"
                        required
                        <?= $canManage ? '' : 'readonly' ?>
                        value="<?= esc(old('name', $menu['name'] ?? '')) ?>"
                    >
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="menu_handle"><?= esc(lang('Menus.Admin.handle')) ?></label>
                    <input
                        class="form-control font-monospace"
                        id="menu_handle"
                        name="handle"
                        maxlength="100"
                        pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                        <?= $canManage ? '' : 'readonly' ?>
                        value="<?= esc(old('handle', $menu['handle'] ?? '')) ?>"
                    >
                    <div class="form-hint"><?= esc(lang('Menus.Admin.handleHint')) ?></div>
                </div>

                <div class="col-12">
                    <label class="form-label" for="menu_description"><?= esc(lang('Menus.Admin.descriptionField')) ?></label>
                    <input
                        class="form-control"
                        id="menu_description"
                        name="description"
                        maxlength="255"
                        <?= $canManage ? '' : 'readonly' ?>
                        value="<?= esc(old('description', $menu['description'] ?? '')) ?>"
                    >
                </div>
            </div>

            <?php if ($canManage): ?>
                <div class="mt-3">
                    <button class="btn btn-primary" type="submit">
                        <i class="ti ti-device-floppy me-1"></i><?= esc(lang('Menus.Admin.saveMenu')) ?>
                    </button>
                </div>
            <?php endif ?>
        </form>
    </div>
</div>

<?php if ($isEdit): ?>
    <div class="alert alert-info mb-4">
        <i class="ti ti-layout-navbar me-2"></i><?= esc(lang('Menus.Admin.themeHint')) ?>
    </div>

    <div class="row row-cards" id="menu-items">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header d-flex flex-wrap align-items-start gap-3">
                    <div class="flex-fill min-w-0">
                        <h3 class="card-title"><?= esc(lang('Menus.Admin.structure')) ?></h3>
                        <div class="text-secondary small mt-1"><?= esc(lang('Menus.Admin.structureHelp')) ?></div>
                    </div>
                    <?php if ($canManage && $items !== []): ?>
                        <button class="btn btn-primary btn-sm flex-shrink-0 ms-md-auto" id="save-structure-button" type="submit" form="menu-structure-form" disabled>
                            <i class="ti ti-device-floppy me-1"></i><?= esc(lang('Menus.Admin.saveStructure')) ?>
                        </button>
                    <?php endif ?>
                </div>

                <div class="card-body">
                    <?php if ($items === []): ?>
                        <div class="empty py-4">
                            <div class="empty-img"><i class="ti ti-list-tree" style="font-size:3rem"></i></div>
                            <p class="empty-title"><?= esc(lang('Menus.Admin.items')) ?></p>
                            <p class="empty-subtitle text-secondary"><?= esc(lang('Menus.Admin.addItem')) ?></p>
                        </div>
                    <?php else: ?>
                        <?= view('MCMS\\Modules\\Menus\\Views\\_tree', [
                            'nodes' => $tree,
                            'menuId' => $menu['id'],
                            'canManage' => $canManage,
                            'isRoot' => true,
                        ]) ?>
                    <?php endif ?>
                </div>
            </div>

            <?php if ($canManage): ?>
                <form method="post" id="menu-structure-form" action="<?= site_url('admin/menus/' . $menu['id'] . '/structure') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="structure" id="menu-structure-value" value="">
                </form>
            <?php endif ?>
        </div>

        <div class="col-xl-4" id="menu-item-form">
            <?php if ($canManage): ?>
                <div class="card position-sticky" style="top:1rem">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="ti <?= $editItem === null ? 'ti-plus' : 'ti-pencil' ?> me-2"></i>
                            <?= esc(lang($editItem === null ? 'Menus.Admin.addItem' : 'Menus.Admin.editItem')) ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <form method="post" action="<?= site_url('admin/menus/' . $menu['id'] . '/items/save') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="item_id" value="<?= (int) ($editItem['id'] ?? 0) ?>">

                            <div class="mb-3">
                                <label class="form-label" for="item_label"><?= esc(lang('Menus.Admin.label')) ?></label>
                                <input class="form-control" id="item_label" name="label" maxlength="190" required value="<?= esc($itemLabel) ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="item_target"><?= esc(lang('Menus.Admin.target')) ?></label>
                                <input class="form-control font-monospace" id="item_target" name="target" maxlength="500" placeholder="/about" value="<?= esc($itemTarget) ?>">
                                <div class="form-hint"><?= esc(lang('Menus.Admin.targetHint')) ?></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="item_parent"><?= esc(lang('Menus.Admin.parent')) ?></label>
                                <select class="form-select" id="item_parent" name="parent_id">
                                    <option value=""><?= esc(lang('Menus.Admin.root')) ?></option>
                                    <?php foreach ($parentOptions as $option): ?>
                                        <?php if ($editItem !== null && $option['id'] === (int) $editItem['id']) continue; ?>
                                        <option value="<?= (int) $option['id'] ?>" <?= (string) $itemParent === (string) $option['id'] ? 'selected' : '' ?>>
                                            <?= esc($option['label']) ?>
                                        </option>
                                    <?php endforeach ?>
                                </select>
                            </div>

                            <label class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="enabled" value="1" <?= $itemEnabled ? 'checked' : '' ?>>
                                <span class="form-check-label"><?= esc(lang('Menus.Admin.enabled')) ?></span>
                            </label>

                            <label class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="open_in_new" value="1" <?= $itemOpenNew ? 'checked' : '' ?>>
                                <span class="form-check-label"><?= esc(lang('Menus.Admin.openInNew')) ?></span>
                            </label>

                            <div class="d-flex flex-wrap gap-2">
                                <button class="btn btn-primary" type="submit">
                                    <i class="ti ti-device-floppy me-1"></i><?= esc(lang('Menus.Admin.saveItem')) ?>
                                </button>
                                <?php if ($editItem !== null): ?>
                                    <a class="btn btn-outline-secondary" href="<?= site_url('admin/menus/' . $menu['id'] . '/edit#menu-item-form') ?>">
                                        <?= esc(lang('Menus.Admin.cancelEdit')) ?>
                                    </a>
                                <?php endif ?>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif ?>
        </div>
    </div>

    <?php if ($canManage && $items !== []): ?>
        <script>
        (() => {
            const root = document.querySelector('[data-menu-tree-root]');
            const value = document.getElementById('menu-structure-value');
            const saveButton = document.getElementById('save-structure-button');

            if (!root || !value || !saveButton) {
                return;
            }

            let dragged = null;

            const directItems = (list) =>
                Array.from(list.children).filter((node) => node.classList.contains('menu-tree-item'));

            const childList = (item) =>
                Array.from(item.children).find((node) => node.classList.contains('menu-tree-list'));

            const serialize = () => {
                const entries = [];

                const walk = (list, parentId = null) => {
                    directItems(list).forEach((item, index) => {
                        const id = Number(item.dataset.id);
                        entries.push({
                            id,
                            parent_id: parentId,
                            position: (index + 1) * 10,
                        });

                        const children = childList(item);
                        if (children) {
                            walk(children, id);
                        }
                    });
                };

                walk(root);
                value.value = JSON.stringify(entries);
            };

            const changed = () => {
                serialize();
                saveButton.disabled = false;
            };

            root.addEventListener('dragstart', (event) => {
                const item = event.target.closest('.menu-tree-item');
                if (!item || !root.contains(item)) {
                    return;
                }

                dragged = item;
                item.classList.add('is-dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', item.dataset.id);
            });

            root.addEventListener('dragover', (event) => {
                if (!dragged) {
                    return;
                }

                const target = event.target.closest('.menu-tree-item');
                if (!target || target === dragged || dragged.contains(target)) {
                    return;
                }

                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';

                root.querySelectorAll('.is-drop-target').forEach((node) => node.classList.remove('is-drop-target'));
                target.classList.add('is-drop-target');

                const row = Array.from(target.children).find((node) => node.classList.contains('menu-tree-row'));
                if (!row) {
                    return;
                }

                const rect = row.getBoundingClientRect();
                const nest = event.clientX > rect.left + Math.min(120, rect.width * .35);

                if (nest) {
                    const children = childList(target);
                    if (children && dragged.parentElement !== children) {
                        children.appendChild(dragged);
                        changed();
                    }
                    return;
                }

                const before = event.clientY < rect.top + rect.height / 2;
                const parent = target.parentElement;
                const reference = before ? target : target.nextElementSibling;

                if (dragged.parentElement !== parent || dragged !== reference) {
                    parent.insertBefore(dragged, reference);
                    changed();
                }
            });

            root.addEventListener('drop', (event) => {
                if (dragged) {
                    event.preventDefault();
                    changed();
                }
            });

            root.addEventListener('dragend', () => {
                root.querySelectorAll('.is-drop-target').forEach((node) => node.classList.remove('is-drop-target'));
                if (dragged) {
                    dragged.classList.remove('is-dragging');
                }
                dragged = null;
            });

            serialize();
        })();
        </script>
    <?php endif ?>
<?php endif ?>

<?= $this->endSection() ?>