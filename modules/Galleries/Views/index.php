<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h2 class="page-title"><?= esc(lang('Galleries.Admin.title')) ?> <span class="text-secondary">(<?= (int) $count ?>)</span></h2>
    <?php if ($canCreate): ?><a class="btn btn-primary" href="<?= site_url('admin/galleries/create') ?>"><?= esc(lang('Galleries.Admin.create')) ?></a><?php endif ?>
</div>
<form method="get" class="row g-2 mb-3">
    <div class="col-md"><input class="form-control" type="search" name="q" value="<?= esc($query, 'attr') ?>" placeholder="<?= esc(lang('Galleries.Admin.search'), 'attr') ?>"></div>
    <div class="col-md-3"><select class="form-select" name="status">
        <option value=""><?= esc(lang('Galleries.Admin.allStatuses')) ?></option>
        <?php foreach (['draft', 'published'] as $value): ?><option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= esc(lang('Galleries.Admin.' . $value)) ?></option><?php endforeach ?>
    </select></div>
    <div class="col-auto"><button class="btn btn-outline-primary"><?= esc(lang('MCMS.common.search')) ?></button></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-vcenter card-table">
    <thead><tr><th><?= esc(lang('MCMS.common.title')) ?></th><th><?= esc(lang('MCMS.common.status')) ?></th><th><?= esc(lang('Galleries.Admin.images')) ?></th><th></th></tr></thead>
    <tbody>
    <?php if ($albums === []): ?><tr><td colspan="4"><?= esc(lang('Galleries.Admin.empty')) ?></td></tr><?php endif ?>
    <?php foreach ($albums as $album): ?><tr>
        <td><strong><?= esc($album['title']) ?></strong><div class="text-secondary"><code><?= esc($album['slug']) ?></code></div></td>
        <td><?= esc(lang('Galleries.Admin.' . $album['status'])) ?></td><td><?= count($album['images']) ?></td>
        <td><div class="btn-list flex-nowrap">
            <?php if ($album['is_live']): ?><a class="btn btn-sm btn-outline-secondary" href="<?= esc(site_url(ltrim($album['url'], '/')), 'attr') ?>" target="_blank" rel="noopener"><?= esc(lang('MCMS.common.open')) ?></a><?php endif ?>
            <?php if ($canEdit): ?><a class="btn btn-sm btn-outline-primary" href="<?= site_url('admin/galleries/' . $album['id'] . '/edit') ?>"><?= esc(lang('Galleries.Admin.edit')) ?></a><?php endif ?>
            <?php if ($canDelete): ?><form method="post" action="<?= site_url('admin/galleries/' . $album['id'] . '/delete') ?>" onsubmit="return confirm(<?= esc(json_encode(lang('Galleries.Admin.deleteConfirm')), 'attr') ?>);">
                <?= csrf_field() ?><button class="btn btn-sm btn-outline-danger"><?= esc(lang('MCMS.common.delete')) ?></button>
            </form><?php endif ?>
        </div></td>
    </tr><?php endforeach ?>
    </tbody>
</table></div></div>
<?php if ($lastPage > 1): ?>
<nav class="d-flex justify-content-between align-items-center mt-3" aria-label="<?= esc(lang('Galleries.Admin.title'), 'attr') ?>">
    <div><?php if ($page > 1): ?><a class="btn btn-outline-secondary" href="<?= esc(site_url('admin/galleries') . '?' . http_build_query(['q' => $query, 'status' => $status, 'page' => $page - 1]), 'attr') ?>"><?= esc(lang('MCMS.common.previousPage')) ?></a><?php endif ?></div>
    <span><?= (int) $page ?> / <?= (int) $lastPage ?></span>
    <div><?php if ($page < $lastPage): ?><a class="btn btn-outline-secondary" href="<?= esc(site_url('admin/galleries') . '?' . http_build_query(['q' => $query, 'status' => $status, 'page' => $page + 1]), 'attr') ?>"><?= esc(lang('MCMS.common.nextPage')) ?></a><?php endif ?></div>
</nav>
<?php endif ?>
<?= $this->endSection() ?>
