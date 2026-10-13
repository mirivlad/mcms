<?= $this->extend('auth/layout') ?>
<?= $this->section('title') ?><?= esc(lang('MCMS.account.order')) ?> #<?= (int) $order['id'] ?><?= $this->endSection() ?>
<?= $this->section('main') ?>
<h1 class="h3 mb-3"><?= esc(lang('MCMS.account.order')) ?> #<?= (int) $order['id'] ?></h1>
<p><?= esc(lang('MCMS.account.status')) ?>: <?= esc(lang('Orders.status.' . $order['status'])) ?></p>
<p><?= esc(lang('MCMS.account.date')) ?>: <?= esc((string) $order['created_at']) ?></p>
<div class="table-responsive"><table class="table">
    <thead><tr><th><?= esc(lang('MCMS.account.product')) ?></th><th><?= esc(lang('MCMS.account.quantity')) ?></th><th><?= esc(lang('MCMS.account.total')) ?></th></tr></thead>
    <tbody><?php foreach ($items as $item): ?><tr>
        <td><?= esc((string) $item['product_title']) ?> — <?= esc((string) $item['variant_label']) ?></td>
        <td><?= (int) $item['quantity'] ?></td>
        <td><?= esc(number_format((int) $item['subtotal_minor'] / 100, 2, '.', ' ') . ' ' . $item['currency']) ?></td>
    </tr><?php endforeach ?></tbody>
</table></div>
<p class="fw-bold"><?= esc(lang('MCMS.account.total')) ?>: <?= esc(number_format((int) $order['total_minor'] / 100, 2, '.', ' ') . ' ' . $order['currency']) ?></p>
<p><a href="<?= site_url('account/orders') ?>"><?= esc(lang('MCMS.account.backToOrders')) ?></a></p>
<?= $this->endSection() ?>
