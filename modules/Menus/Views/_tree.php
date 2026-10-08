<?php $isRoot = $isRoot ?? false; ?>
<ul class="menu-tree-list <?= $isRoot ? 'menu-tree-root' : '' ?>" <?= $isRoot ? 'data-menu-tree-root' : '' ?>>
    <?php foreach ($nodes as $item): ?>
        <li class="menu-tree-item" data-id="<?= (int) $item['id'] ?>" <?= $canManage ? 'draggable="true"' : '' ?>>
            <div class="menu-tree-row">
                <?php if ($canManage): ?>
                    <span class="menu-drag-handle" title="<?= esc(lang('Menus.Admin.structureHelp'), 'attr') ?>">
                        <i class="ti ti-grip-vertical"></i>
                    </span>
                <?php endif ?>
                <div class="min-w-0 flex-fill">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <span class="fw-semibold"><?= esc($item['label']) ?></span>
                        <?php if (! $item['enabled']): ?>
                            <span class="badge bg-secondary-lt"><?= esc(lang('Menus.Admin.inactive')) ?></span>
                        <?php endif ?>
                        <?php if ($item['open_in_new']): ?>
                            <span class="badge bg-azure-lt"><?= esc(lang('Menus.Admin.newWindow')) ?></span>
                        <?php endif ?>
                    </div>
                    <div class="text-secondary small text-truncate">
                        <?php if ($item['target_type'] === 'custom'): ?>
                            <?= ($item['target'] ?? null) === null ? '—' : esc($item['target']) ?>
                        <?php elseif ($item['target_available']): ?>
                            <i class="ti ti-link me-1"></i><?= esc($item['resource_label'] ?? $item['target']) ?>
                            <span class="text-secondary">· <?= esc($item['target_type']) ?></span>
                        <?php else: ?>
                            <span class="text-warning">
                                <i class="ti ti-unlink me-1"></i><?= esc(sprintf(lang('Menus.Admin.resourceUnavailable'), $item['target_type'], $item['target'])) ?>
                            </span>
                        <?php endif ?>
                    </div>
                </div>
                <?php if ($canManage): ?>
                    <div class="d-flex gap-1 ms-2">
                        <a class="btn btn-icon btn-ghost-secondary btn-sm admin-icon-action" href="<?= site_url('admin/menus/' . $menuId . '/edit?item=' . $item['id']) ?>#menu-item-form" title="<?= esc(lang('Menus.Admin.editAction'), 'attr') ?>" aria-label="<?= esc(lang('Menus.Admin.editAction'), 'attr') ?>" data-bs-toggle="tooltip" data-bs-container="body">
                            <i aria-hidden="true" class="ti ti-pencil"></i>
                        </a>
                        <form method="post" action="<?= site_url('admin/menus/' . $menuId . '/items/' . $item['id'] . '/delete') ?>" onsubmit="return confirm(<?= esc(json_encode(lang('Menus.Admin.deleteItemConfirm')), 'attr') ?>)">
                            <?= csrf_field() ?>
                            <button class="btn btn-icon btn-ghost-danger btn-sm admin-icon-action" type="submit" title="<?= esc(lang('Menus.Admin.deleteAction'), 'attr') ?>" aria-label="<?= esc(lang('Menus.Admin.deleteAction'), 'attr') ?>" data-bs-toggle="tooltip" data-bs-container="body">
                                <i aria-hidden="true" class="ti ti-trash"></i>
                            </button>
                        </form>
                    </div>
                <?php endif ?>
            </div>
            <?= view('MCMS\\Modules\\Menus\\Views\\_tree', [
                'nodes' => $item['children'] ?? [],
                'menuId' => $menuId,
                'canManage' => $canManage,
                'isRoot' => false,
            ]) ?>
        </li>
    <?php endforeach ?>
</ul>