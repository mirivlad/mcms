<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$money = static fn (int $minor, string $currency): string => number_format(intdiv($minor, 100), 0, ',', ' ')
    . ',' . str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT) . ' ' . $currency;
?>
<div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-4">
    <h1 class="page-title"><?= esc(lang('Orders.Reports.title')) ?></h1>
    <a class="btn" href="<?= site_url('admin/orders') ?>"><?= esc(lang('Orders.back')) ?></a>
</div>
<p class="text-secondary"><?= esc(lang('Orders.Reports.dateHint')) ?></p>
<form class="row g-3 align-items-end mb-4" method="get">
    <div class="col-sm-4 col-lg-3">
        <label class="form-label" for="report-from"><?= esc(lang('Orders.Reports.from')) ?></label>
        <input class="form-control" id="report-from" type="date" name="from" value="<?= esc($filters['from'], 'attr') ?>">
    </div>
    <div class="col-sm-4 col-lg-3">
        <label class="form-label" for="report-to"><?= esc(lang('Orders.Reports.to')) ?></label>
        <input class="form-control" id="report-to" type="date" name="to" value="<?= esc($filters['to'], 'attr') ?>">
    </div>
    <div class="col-sm-4 col-lg-3">
        <label class="form-label" for="report-status"><?= esc(lang('Orders.statusTitle')) ?></label>
        <select class="form-select" id="report-status" name="status">
            <option value=""><?= esc(lang('Orders.allStatuses')) ?></option>
            <?php foreach ($statuses as $status): ?>
                <option value="<?= esc($status, 'attr') ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= esc(lang('Orders.status.' . $status)) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <div class="col-lg-auto"><button class="btn btn-primary" type="submit"><?= esc(lang('Orders.Reports.apply')) ?></button></div>
</form>
<?php if ($error !== null): ?>
    <div class="alert alert-danger" role="alert"><?= esc($error) ?></div>
<?php else: ?>
    <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
        <span class="me-auto text-secondary"><?= esc(lang('Orders.Reports.count')) ?>: <?= array_sum(array_column($summary, 'order_count')) ?></span>
        <?php if ($canExport): ?>
            <?php foreach (['orders', 'items'] as $format): ?>
                <a class="btn btn-outline-secondary" href="<?= esc(site_url('admin/orders/export') . '?' . http_build_query([...$filters, 'format' => $format]), 'attr') ?>">
                    <i class="ti ti-download me-1" aria-hidden="true"></i><?= esc(lang('Orders.Reports.export' . ucfirst($format))) ?>
                </a>
            <?php endforeach ?>
        <?php endif ?>
    </div>
    <div class="card"><div class="table-responsive">
        <table class="table table-vcenter card-table">
            <caption class="visually-hidden"><?= esc(lang('Orders.Reports.title')) ?></caption>
            <thead><tr>
                <th scope="col"><?= esc(lang('Orders.statusTitle')) ?></th>
                <th scope="col"><?= esc(lang('Orders.Reports.currency')) ?></th>
                <th scope="col" class="text-end"><?= esc(lang('Orders.Reports.count')) ?></th>
                <th scope="col" class="text-end"><?= esc(lang('Orders.itemsSubtotal')) ?></th>
                <th scope="col" class="text-end"><?= esc(lang('Orders.deliveryCost')) ?></th>
                <th scope="col" class="text-end"><?= esc(lang('Orders.sum')) ?></th>
            </tr></thead>
            <tbody>
                <?php foreach ($summary as $row): ?>
                    <tr>
                        <th scope="row"><?= esc(lang('Orders.status.' . $row['status'])) ?></th>
                        <td><?= esc($row['currency']) ?></td><td class="text-end"><?= $row['order_count'] ?></td>
                        <?php foreach (['subtotal_minor', 'delivery_minor', 'total_minor'] as $field): ?>
                            <td class="text-end text-nowrap"><?= esc($money($row[$field], $row['currency'])) ?></td>
                        <?php endforeach ?>
                    </tr>
                <?php endforeach ?>
                <?php if ($summary === []): ?><tr><td colspan="6" class="text-secondary py-4"><?= esc(lang('Orders.Reports.empty')) ?></td></tr><?php endif ?>
            </tbody>
        </table>
    </div></div>
    <p class="text-secondary mt-3"><?= esc(lang('Orders.Reports.totalsHint')) ?></p>
    <?php if ($canExport): ?><p class="text-secondary small"><?= esc(lang('Orders.Reports.csvHint')) ?></p><?php endif ?>
<?php endif ?>
<?= $this->endSection() ?>
