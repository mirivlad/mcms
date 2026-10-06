<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-4">
    <div>
        <h1 class="page-title"><?= esc(lang('Stock.title')) ?></h1>
        <span class="text-muted"><?= esc(lang('Stock.count')) ?>: <?= $count ?></span>
    </div>
    <a class="btn" href="<?= site_url('admin/catalogue') ?>"><?= esc(lang('Stock.backStore')) ?></a>
</div>

<form class="d-flex gap-2 mb-4" method="get">
    <input class="form-control" name="q" value="<?= esc($query, 'attr') ?>" placeholder="<?= esc(lang('Stock.search'), 'attr') ?>" aria-label="<?= esc(lang('Stock.search'), 'attr') ?>">
    <button class="btn" type="submit"><?= esc(lang('Stock.searchButton')) ?></button>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr>
                <th><?= esc(lang('Stock.product')) ?></th>
                <th><?= esc(lang('Stock.variant')) ?></th>
                <th><?= esc(lang('Stock.sku')) ?></th>
                <th><?= esc(lang('Stock.onHand')) ?></th>
                <th><?= esc(lang('Stock.reserved')) ?></th>
                <th><?= esc(lang('Stock.available')) ?></th>
                <?php if ($canManage): ?><th class="w-1"></th><?php endif ?>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $row): $stock = $row['stock']; ?>
                <tr>
                    <td><a href="<?= site_url('admin/catalogue/' . $row['product_id'] . '/edit') ?>"><?= esc($row['product_title']) ?></a></td>
                    <td><?= esc($row['label'] !== '' ? $row['label'] : '—') ?></td>
                    <td><code><?= esc($row['sku']) ?></code></td>
                    <?php if ($canManage): ?>
                        <td colspan="3">
                            <form class="d-flex align-items-center gap-2 flex-wrap" method="post" action="<?= site_url('admin/stock/variant/' . $row['id']) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="return" value="<?= esc($returnPath, 'attr') ?>">
                                <input class="form-control form-control-sm" style="width:7rem" type="number" min="<?= $stock['reserved'] ?>" max="2000000000" step="1" name="quantity" value="<?= $stock['on_hand'] ?>" aria-label="<?= esc(lang('Stock.onHand'), 'attr') ?>">
                                <span class="badge bg-secondary-lt"><?= esc(lang('Stock.reserved')) ?>: <?= $stock['reserved'] ?></span>
                                <span class="badge bg-azure-lt"><?= esc(lang('Stock.available')) ?>: <?= $stock['available'] ?></span>
                                <input class="form-control form-control-sm" style="min-width:12rem;max-width:20rem" name="note" maxlength="255" placeholder="<?= esc(lang('Stock.note'), 'attr') ?>">
                                <button class="btn btn-sm btn-primary" type="submit"><?= esc(lang('Stock.save')) ?></button>
                            </form>
                        </td>
                        <td></td>
                    <?php else: ?>
                        <td><?= $stock['on_hand'] ?></td><td><?= $stock['reserved'] ?></td><td><?= $stock['available'] ?></td>
                    <?php endif ?>
                </tr>
            <?php endforeach ?>
            <?php if ($rows === []): ?><tr><td colspan="<?= $canManage ? 7 : 6 ?>" class="text-muted py-4"><?= esc(lang('Stock.empty')) ?></td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($lastPage > 1): ?>
<nav class="d-flex gap-3 justify-content-center mt-3">
    <?php if ($page > 1): ?><a href="?<?= esc(http_build_query(['q' => $query, 'page' => $page - 1]), 'attr') ?>"><?= esc(lang('Stock.previous')) ?></a><?php endif ?>
    <span><?= $page ?> / <?= $lastPage ?></span>
    <?php if ($page < $lastPage): ?><a href="?<?= esc(http_build_query(['q' => $query, 'page' => $page + 1]), 'attr') ?>"><?= esc(lang('Stock.next')) ?></a><?php endif ?>
</nav>
<?php endif ?>
<?= $this->endSection() ?>
