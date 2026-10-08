<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4 d-flex flex-wrap align-items-center gap-3">
    <h2 class="page-title"><?= esc(lang('News.Admin.categories')) ?></h2>
    <a class="btn btn-outline-secondary ms-auto" href="<?= site_url('admin/news') ?>"><?= esc(lang('MCMS.common.back')) ?></a>
    <button class="btn btn-primary" type="button" data-category-create><?= esc(lang('News.Admin.createCategory')) ?></button>
</div>
<div class="card"><div class="table-responsive"><table class="table card-table table-vcenter">
    <thead><tr><th><?= esc(lang('MCMS.common.title')) ?></th><th><?= esc(lang('News.Admin.slug')) ?></th><th></th></tr></thead>
    <tbody><?php if ($categories === []): ?><tr><td colspan="3"><?= esc(lang('News.Admin.noCategories')) ?></td></tr><?php endif ?>
    <?php foreach ($categories as $category): ?>
    <tr><td><?= esc($category['name']) ?></td><td><code><?= esc($category['slug']) ?></code></td><td><div class="btn-list flex-nowrap">
        <button class="btn btn-outline-primary btn-sm btn-icon admin-icon-action" type="button" data-category-edit="<?= esc(json_encode($category, JSON_UNESCAPED_UNICODE), 'attr') ?>" aria-label="<?= esc(lang('MCMS.common.edit'), 'attr') ?>" title="<?= esc(lang('MCMS.common.edit'), 'attr') ?>" data-bs-toggle="tooltip" data-bs-container="body"><i class="ti ti-pencil" aria-hidden="true"></i></button>
        <button class="btn btn-outline-danger btn-sm btn-icon admin-icon-action" type="button" data-category-delete="<?= (int) $category['id'] ?>" data-category-name="<?= esc($category['name'], 'attr') ?>" aria-label="<?= esc(lang('MCMS.common.delete'), 'attr') ?>" title="<?= esc(lang('MCMS.common.delete'), 'attr') ?>" data-bs-toggle="tooltip" data-bs-container="body"><i class="ti ti-trash" aria-hidden="true"></i></button>
    </div></td></tr>
    <?php endforeach ?></tbody>
</table></div></div>
<dialog id="category-dialog" aria-labelledby="category-heading" style="width:min(520px,94vw);border:0;border-radius:12px;background:var(--tblr-bg-surface);color:var(--tblr-body-color)">
    <form method="post" action="<?= site_url('admin/news/categories/save') ?>">
        <?= csrf_field() ?><input type="hidden" name="category_form" value="1"><input type="hidden" name="id" value="<?= esc(old('id', ''), 'attr') ?>">
        <div class="d-flex justify-content-between align-items-center mb-3"><h3 id="category-heading"></h3><button class="btn-close" type="button" data-category-close aria-label="<?= esc(lang('MCMS.common.cancel'), 'attr') ?>"></button></div>
        <label class="form-label" for="category-name"><?= esc(lang('MCMS.common.title')) ?></label><input class="form-control mb-3" name="name" id="category-name" maxlength="120" required value="<?= esc(old('name', ''), 'attr') ?>">
        <label class="form-label" for="category-slug"><?= esc(lang('News.Admin.slug')) ?></label><input class="form-control mb-3" name="slug" id="category-slug" maxlength="190" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?= esc(old('slug', ''), 'attr') ?>">
        <p class="form-hint"><?= esc(lang('News.Admin.categorySlugHint')) ?></p>
        <div class="text-end"><button type="submit" class="btn btn-primary"><?= esc(lang('MCMS.common.save')) ?></button></div>
    </form>
</dialog>
<dialog id="category-delete-dialog" aria-labelledby="category-delete-heading" style="width:min(500px,94vw);border:0;border-radius:12px;background:var(--tblr-bg-surface);color:var(--tblr-body-color)">
    <h3 id="category-delete-heading"><?= esc(lang('News.Admin.deleteCategory')) ?></h3><p data-category-delete-name></p>
    <p><?= esc(lang('News.Admin.categoryDeleteHint')) ?></p>
    <form method="post" action="<?= site_url('admin/news/categories/delete') ?>">
        <?= csrf_field() ?><input type="hidden" name="id"><input type="hidden" name="confirm" value="1">
        <div class="d-flex justify-content-end gap-2"><button class="btn" type="button" data-category-delete-cancel><?= esc(lang('MCMS.common.cancel')) ?></button><button class="btn btn-danger" type="submit"><?= esc(lang('MCMS.common.delete')) ?></button></div>
    </form>
</dialog>
<script>
(() => {
    const dialog = document.getElementById('category-dialog');
    const deletion = document.getElementById('category-delete-dialog');
    const form = dialog.querySelector('form');
    let trigger;
    function open(button, category) {
        trigger = button;
        form.elements.id.value = category?.id || '';
        form.elements.name.value = category?.name || '';
        form.elements.slug.value = category?.slug || '';
        heading(); dialog.showModal(); form.elements.name.focus();
    }
    function heading() {
        document.getElementById('category-heading').textContent = form.elements.id.value ? <?= json_encode(lang('News.Admin.editCategory')) ?> : <?= json_encode(lang('News.Admin.createCategory')) ?>;
    }
    document.querySelector('[data-category-create]').addEventListener('click', event => open(event.currentTarget, null));
    document.querySelectorAll('[data-category-edit]').forEach(button => button.addEventListener('click', () => open(button, JSON.parse(button.dataset.categoryEdit))));
    dialog.querySelector('[data-category-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => trigger?.focus());
    document.querySelectorAll('[data-category-delete]').forEach(button => button.addEventListener('click', () => {
        trigger = button; deletion.querySelector('[name="id"]').value = button.dataset.categoryDelete;
        deletion.querySelector('[data-category-delete-name]').textContent = button.dataset.categoryName; deletion.showModal();
    }));
    deletion.querySelector('[data-category-delete-cancel]').addEventListener('click', () => deletion.close());
    deletion.addEventListener('close', () => trigger?.focus());
    <?php if (old('category_form') === '1'): ?>heading(); dialog.showModal();<?php endif ?>
})();
</script>
<?= $this->endSection() ?>
