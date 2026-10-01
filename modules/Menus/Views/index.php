<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md">
            <div class="page-pretitle"><?= esc(lang('MCMS.common.module')) ?></div>
            <h2 class="page-title"><?= esc(lang('Menus.Admin.title')) ?></h2>
            <div class="text-secondary mt-1"><?= esc(sprintf(lang('Menus.Admin.count'), count($menus))) ?></div>
        </div>
        <?php if ($canManage): ?>
            <div class="col-12 col-md-auto ms-md-auto">
                <a class="btn btn-primary" href="<?= site_url('admin/menus/create') ?>">
                    <i class="ti ti-plus me-1"></i><?= esc(lang('Menus.Admin.create')) ?>
                </a>
            </div>
        <?php endif ?>
    </div>
</div>

<div class="alert alert-info">
    <i class="ti ti-info-circle me-2"></i><?= esc(lang('Menus.Admin.themeHint')) ?>
</div>

<div class="card">
    <?php if ($menus === []): ?>
        <div class="empty">
            <div class="empty-img"><i class="ti ti-menu-2" style="font-size:4rem"></i></div>
            <p class="empty-title"><?= esc(lang('Menus.Admin.empty')) ?></p>
            <?php if ($canManage): ?>
                <div class="empty-action">
                    <a class="btn btn-primary" href="<?= site_url('admin/menus/create') ?>">
                        <i class="ti ti-plus me-1"></i><?= esc(lang('Menus.Admin.create')) ?>
                    </a>
                </div>
            <?php endif ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                <tr>
                    <th><?= esc(lang('Menus.Admin.name')) ?></th>
                    <th><?= esc(lang('Menus.Admin.handle')) ?></th>
                    <th><?= esc(lang('Menus.Admin.items')) ?></th>
                    <th class="w-1"></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($menus as $menu): ?>
                    <tr>
                        <td>
                            <a class="fw-semibold text-reset" href="<?= site_url('admin/menus/' . $menu['id'] . '/edit') ?>">
                                <?= esc($menu['name']) ?>
                            </a>
                            <?php if (($menu['description'] ?? null) !== null): ?>
                                <div class="text-secondary small mt-1"><?= esc($menu['description']) ?></div>
                            <?php endif ?>
                        </td>
                        <td><code><?= esc($menu['handle']) ?></code></td>
                        <td><?= esc(sprintf(lang('Menus.Admin.itemCount'), $menu['item_count'])) ?></td>
                        <td>
                            <?php if ($canManage): ?>
                                <form method="post" action="<?= site_url('admin/menus/' . $menu['id'] . '/delete') ?>" onsubmit="return confirm(<?= esc(json_encode(lang('Menus.Admin.deleteMenuConfirm')), 'attr') ?>)">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-icon btn-ghost-danger" type="submit" title="<?= esc(lang('Menus.Admin.deleteMenu'), 'attr') ?>">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </form>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</div>
<?= $this->endSection() ?>