<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-4">
    <div>
        <h1 class="page-title"><?= esc(lang('Orders.title')) ?></h1>
        <span class="text-muted"><?= esc(lang('Orders.title')) ?>: <?= $count ?></span>
    </div>
</div>

<form class="row g-2 mb-4" method="get">
    <div class="col-md">
        <input class="form-control" name="q" value="<?= esc($query, 'attr') ?>" placeholder="<?= esc(lang('Orders.search'), 'attr') ?>">
    </div>
    <div class="col-md-3">
        <select class="form-select" name="status">
            <option value=""><?= esc(lang('Orders.allStatuses')) ?></option>
            <?php foreach ($statuses as $item): ?>
                <option value="<?= esc($item, 'attr') ?>" <?= $status === $item ? 'selected' : '' ?>><?= esc(lang('Orders.status.' . $item)) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <div class="col-md-auto"><button class="btn" type="submit"><?= esc(lang('Orders.find')) ?></button></div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr>
                <th><?= esc(lang('Orders.number')) ?></th>
                <th><?= esc(lang('Orders.createdAt')) ?></th>
                <th><?= esc(lang('Orders.customer')) ?></th>
                <th><?= esc(lang('Orders.contacts')) ?></th>
                <th><?= esc(lang('Orders.statusTitle')) ?></th>
                <th class="text-end"><?= esc(lang('Orders.sum')) ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><a class="fw-bold" href="<?= site_url('admin/orders/' . $order['id']) ?>">#<?= $order['id'] ?></a></td>
                    <td><?= esc($order['created_at']) ?></td>
                    <td><?= esc($order['customer_name']) ?></td>
                    <td>
                        <div><?= esc($order['phone']) ?></div>
                        <?php if ($order['email'] !== null): ?><div class="text-muted small"><?= esc($order['email']) ?></div><?php endif ?>
                    </td>
                    <td><span class="badge bg-secondary-lt"><?= esc($order['status_label']) ?></span></td>
                    <td class="text-end fw-semibold"><?= esc($order['subtotal_display']) ?></td>
                </tr>
            <?php endforeach ?>
            <?php if ($orders === []): ?><tr><td colspan="6" class="text-muted py-4"><?= esc(lang('Orders.notFound')) ?></td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($lastPage > 1): ?>
<nav class="d-flex gap-3 justify-content-center mt-3">
    <?php if ($page > 1): ?><a href="?<?= esc(http_build_query(['q' => $query, 'status' => $status, 'page' => $page - 1]), 'attr') ?>"><?= esc(lang('Orders.previous')) ?></a><?php endif ?>
    <span><?= $page ?> / <?= $lastPage ?></span>
    <?php if ($page < $lastPage): ?><a href="?<?= esc(http_build_query(['q' => $query, 'status' => $status, 'page' => $page + 1]), 'attr') ?>"><?= esc(lang('Orders.next')) ?></a><?php endif ?>
</nav>
<?php endif ?>
<?= $this->endSection() ?>
