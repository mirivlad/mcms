<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-4"><div><h1 class="page-title"><?= esc(lang('Catalogue.manageProducts')) ?></h1><span class="text-muted"><?= esc(lang('Catalogue.count')) ?>: <?= $count ?></span></div><?php if ($canCreate): ?><a class="btn btn-primary" href="<?= site_url('admin/catalogue/create') ?>"><?= esc(lang('Catalogue.create')) ?></a><?php endif ?></div>
<a class="btn mb-3" href="<?= site_url('admin/catalogue') ?>"><?= esc(lang('Catalogue.backStore')) ?></a>
<form class="d-flex gap-2 mb-4" method="get"><input class="form-control" name="q" value="<?= esc($query, 'attr') ?>" placeholder="<?= esc(lang('Catalogue.search'), 'attr') ?>" aria-label="<?= esc(lang('Catalogue.search'), 'attr') ?>"><button class="btn" type="submit"><?= esc(lang('Catalogue.search')) ?></button></form>
<div class="card"><div class="list-group list-group-flush">
<?php foreach ($products as $product): ?><div class="list-group-item d-flex align-items-center gap-3 flex-wrap"><div class="flex-fill"><strong><?= esc($product['title']) ?></strong><div class="text-muted small"><?= esc($product['category']) ?> · <?= esc(lang('Catalogue.' . $product['status'])) ?> · <?= esc($product['from_price']) ?></div></div><?php if ($canEdit): ?><a class="btn btn-sm" href="<?= site_url('admin/catalogue/' . $product['id'] . '/edit') ?>"><?= esc(lang('Catalogue.edit')) ?></a><?php endif ?><?php if ($canDelete): ?><button class="btn btn-sm btn-outline-danger" type="button" data-delete-product="<?= $product['id'] ?>" data-title="<?= esc($product['title'], 'attr') ?>"><?= esc(lang('Catalogue.deleteButton')) ?></button><?php endif ?></div><?php endforeach ?>
<?php if ($products === []): ?><div class="card-body text-muted"><?= esc(lang($query === '' ? 'Catalogue.empty' : 'Catalogue.noResults')) ?></div><?php endif ?>
</div></div>
<?php if ($lastPage > 1): ?><nav class="d-flex gap-3 mt-3"><?php if ($page > 1): ?><a href="?<?= esc(http_build_query(['q' => $query, 'page' => $page - 1]), 'attr') ?>"><?= esc(lang('Catalogue.previous')) ?></a><?php endif ?><span><?= $page ?> / <?= $lastPage ?></span><?php if ($page < $lastPage): ?><a href="?<?= esc(http_build_query(['q' => $query, 'page' => $page + 1]), 'attr') ?>"><?= esc(lang('Catalogue.next')) ?></a><?php endif ?></nav><?php endif ?>
<?php if ($canDelete): ?>
<dialog id="delete-product-dialog" class="border rounded p-4" style="width:min(480px,94vw)"><h2 class="h3" data-delete-title></h2><p><?= esc(lang('Catalogue.confirmDelete')) ?></p><form method="post"><?= csrf_field() ?><input type="hidden" name="confirm" value="1"><div class="d-flex justify-content-end gap-2"><button class="btn" type="button" data-delete-cancel><?= esc(lang('Catalogue.cancel')) ?></button><button class="btn btn-danger" type="submit"><?= esc(lang('Catalogue.deleteButton')) ?></button></div></form></dialog>
<script>
(() => {
    const dialog = document.querySelector('#delete-product-dialog');
    document.querySelectorAll('[data-delete-product]').forEach(button => button.addEventListener('click', () => {
        dialog.querySelector('[data-delete-title]').textContent = button.dataset.title;
        dialog.querySelector('form').action = <?= json_encode(site_url('admin/catalogue')) ?> + '/' + button.dataset.deleteProduct + '/delete';
        dialog.showModal();
    }));
    dialog.querySelector('[data-delete-cancel]').addEventListener('click', () => dialog.close());
})();
</script>
<?php endif ?>
<?= $this->endSection() ?>
