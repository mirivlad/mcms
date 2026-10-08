<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-4">
    <div>
        <h1 class="page-title"><?= esc(lang('Delivery.title')) ?></h1>
        <div class="text-muted"><?= esc(lang('Delivery.snapshotNotice')) ?></div>
    </div>
    <?php if ($canManage): ?>
        <a class="btn btn-primary" href="<?= site_url('admin/delivery/create') ?>">
            <i class="ti ti-plus"></i>
            <?= esc(lang('Delivery.create')) ?>
        </a>
    <?php endif ?>
</div>

<?php if (session()->getFlashdata('message')): ?>
    <div class="alert alert-success"><?= esc(session()->getFlashdata('message')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<?php if ($methods === []): ?>
    <div class="card"><div class="card-body text-muted"><?= esc(lang('Delivery.noMethods')) ?></div></div>
<?php else: ?>
    <form method="post" action="<?= site_url('admin/delivery/order') ?>">
        <?= csrf_field() ?>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th style="width:8rem"><?= esc(lang('Delivery.position')) ?></th>
                        <th><?= esc(lang('Delivery.name')) ?></th>
                        <th><?= esc(lang('Delivery.typeTitle')) ?></th>
                        <th class="text-end"><?= esc(lang('Delivery.price')) ?></th>
                        <th><?= esc(lang('Delivery.enabled')) ?></th>
                        <th class="text-end"><?= esc(lang('Delivery.actions')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($methods as $method): ?>
                        <tr>
                            <td>
                                <?php if ($canManage): ?>
                                    <input class="form-control form-control-sm" style="width:6rem" type="number" min="0"
                                           name="position[<?= (int) $method['id'] ?>]" value="<?= (int) $method['position'] ?>">
                                <?php else: ?>
                                    <?= (int) $method['position'] ?>
                                <?php endif ?>
                            </td>
                            <td class="fw-semibold"><?= esc($method['name']) ?></td>
                            <td>
                                <span class="badge <?= $method['type'] === 'delivery' ? 'bg-blue-lt' : 'bg-green-lt' ?>">
                                    <?= esc($method['type_label']) ?>
                                </span>
                            </td>
                            <td class="text-end"><?= esc($method['price_minor'] === 0 ? lang('Delivery.free') : $method['price_display']) ?></td>
                            <td>
                                <?php if ($method['enabled']): ?>
                                    <span class="badge bg-success-lt"><?= esc(lang('Delivery.enabled')) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-lt"><?= esc(lang('Delivery.disabled')) ?></span>
                                <?php endif ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <?php if ($canManage): ?>
                                    <a class="btn btn-sm btn-outline-primary btn-icon admin-icon-action" href="<?= site_url('admin/delivery/' . $method['id']) ?>" aria-label="<?= esc(lang('Delivery.edit'), 'attr') ?>" title="<?= esc(lang('Delivery.edit'), 'attr') ?>" data-bs-toggle="tooltip" data-bs-container="body"><i class="ti ti-pencil" aria-hidden="true"></i></a>
                                    <button class="btn btn-sm btn-outline-danger btn-icon admin-icon-action" type="submit"
                                            form="delete-delivery-<?= (int) $method['id'] ?>"
                                            onclick="return confirm(<?= esc(json_encode(lang('Delivery.confirmDelete'), JSON_UNESCAPED_UNICODE), 'attr') ?>)" aria-label="<?= esc(lang('Delivery.delete'), 'attr') ?>" title="<?= esc(lang('Delivery.delete'), 'attr') ?>" data-bs-toggle="tooltip" data-bs-container="body"><i class="ti ti-trash" aria-hidden="true"></i></button>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <?php if ($canManage): ?>
                <div class="card-footer">
                    <button class="btn btn-primary" type="submit"><?= esc(lang('Delivery.saveOrder')) ?></button>
                </div>
            <?php endif ?>
        </div>
    </form>

    <?php if ($canManage): ?>
        <?php foreach ($methods as $method): ?>
            <form id="delete-delivery-<?= (int) $method['id'] ?>" method="post"
                  action="<?= site_url('admin/delivery/' . $method['id'] . '/delete') ?>">
                <?= csrf_field() ?>
            </form>
        <?php endforeach ?>
    <?php endif ?>
<?php endif ?>

<?= $this->endSection() ?>
