<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-4">
    <div>
        <a href="<?= site_url('admin/orders') ?>" class="text-muted"><?= esc(lang('Orders.back')) ?></a>
        <h1 class="page-title mt-2"><?= esc(lang('Orders.orderNumber', [$order['id']])) ?></h1>
    </div>
    <span class="badge bg-secondary-lt fs-5"><?= esc($order['status_label']) ?></span>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header"><h2 class="card-title"><?= esc(lang('Orders.items')) ?></h2></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th><?= esc(lang('Orders.product')) ?></th><th><?= esc(lang('Orders.sku')) ?></th><th><?= esc(lang('Orders.price')) ?></th><th><?= esc(lang('Orders.quantity')) ?></th><th class="text-end"><?= esc(lang('Orders.subtotal')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($order['items'] as $item): ?>
                        <tr>
                            <td>
                                <strong><?= esc($item['product_title']) ?></strong>
                                <?php if ($item['variant_label'] !== ''): ?><div class="text-muted small"><?= esc($item['variant_label']) ?></div><?php endif ?>
                            </td>
                            <td><code><?= esc($item['sku']) ?></code></td>
                            <td><?= esc($item['unit_price_display']) ?></td>
                            <td><?= $item['quantity'] ?></td>
                            <td class="text-end"><?= esc($item['subtotal_display']) ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                    <tfoot><tr><th colspan="4"><?= esc(lang('Orders.sum')) ?></th><th class="text-end"><?= esc($order['subtotal_display']) ?></th></tr></tfoot>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title"><?= esc(lang('Orders.history')) ?></h2></div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php foreach (array_reverse($order['history']) as $entry): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between gap-3">
                                <div>
                                    <?php if ($entry['from_status'] !== null): ?>
                                        <?= esc(lang('Orders.status.' . $entry['from_status'])) ?> → 
                                    <?php endif ?>
                                    <strong><?= esc(lang('Orders.status.' . $entry['to_status'])) ?></strong>
                                    <?php if ($entry['note'] !== ''): ?><div class="text-muted small mt-1"><?= esc($entry['note']) ?></div><?php endif ?>
                                </div>
                                <span class="text-muted small"><?= esc($entry['created_at']) ?></span>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header"><h2 class="card-title"><?= esc(lang('Orders.customer')) ?></h2></div>
            <div class="card-body">
                <dl class="mb-0">
                    <dt><?= esc(lang('Orders.name')) ?></dt><dd><?= esc($order['customer_name']) ?></dd>
                    <dt><?= esc(lang('Orders.phone')) ?></dt><dd><?= esc($order['phone']) ?></dd>
                    <?php if ($order['email'] !== null): ?><dt><?= esc(lang('Orders.email')) ?></dt><dd><?= esc($order['email']) ?></dd><?php endif ?>
                    <?php if ($order['comment'] !== null): ?><dt><?= esc(lang('Orders.comment')) ?></dt><dd><?= nl2br(esc($order['comment'])) ?></dd><?php endif ?>
                </dl>
            </div>
        </div>

        <?php if ($canManage && $order['allowed_statuses'] !== []): ?>
        <div class="card">
            <div class="card-header"><h2 class="card-title"><?= esc(lang('Orders.changeStatus')) ?></h2></div>
            <form method="post" action="<?= site_url('admin/orders/' . $order['id'] . '/status') ?>">
                <div class="card-body">
                    <?= csrf_field() ?>
                    <label class="form-label"><?= esc(lang('Orders.statusTitle')) ?></label>
                    <select class="form-select mb-3" name="status" required>
                        <?php foreach ($order['allowed_statuses'] as $item): ?>
                            <option value="<?= esc($item, 'attr') ?>"><?= esc(lang('Orders.status.' . $item)) ?></option>
                        <?php endforeach ?>
                    </select>
                    <label class="form-label"><?= esc(lang('Orders.managerNote')) ?></label>
                    <textarea class="form-control" name="note" maxlength="255" rows="3"></textarea>
                </div>
                <div class="card-footer"><button class="btn btn-primary" type="submit"><?= esc(lang('Orders.changeStatus')) ?></button></div>
            </form>
        </div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
