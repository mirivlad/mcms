<?= $this->extend('auth/layout') ?>
<?= $this->section('title') ?><?= esc(lang('MCMS.account.orders')) ?><?= $this->endSection() ?>
<?= $this->section('main') ?>
<h1 class="h3 mb-4"><?= esc(lang('MCMS.account.orders')) ?></h1>
<?php if ($orders === []): ?>
<p><?= esc(lang('MCMS.account.noOrders')) ?></p>
<?php else: ?>
<div class="table-responsive">
<table class="table table-striped align-middle">
    <thead><tr><th><?= esc(lang('MCMS.account.order')) ?></th><th><?= esc(lang('MCMS.account.date')) ?></th><th><?= esc(lang('MCMS.account.status')) ?></th><th><?= esc(lang('MCMS.account.total')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($orders as $order): ?>
    <tr>
        <td><a href="<?= site_url('account/orders/' . (int) $order['id']) ?>">#<?= (int) $order['id'] ?></a></td>
        <td><?= esc((string) $order['created_at']) ?></td>
        <td><?= esc(lang('Orders.status.' . $order['status'])) ?></td>
        <td><?= esc(number_format((int) $order['total_minor'] / 100, 2, '.', ' ') . ' ' . $order['currency']) ?></td>
    </tr>
    <?php endforeach ?>
    </tbody>
</table></div>
<?php endif ?>
<?= $this->endSection() ?>
