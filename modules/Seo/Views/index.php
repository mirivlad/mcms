<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<h2 class="page-title mb-3"><?= esc(lang('Seo.Admin.title')) ?></h2>
<?= view('MCMS\\Modules\\Seo\\Views\\settings', ['rules' => $rules]) ?>
<p><a href="<?= site_url('sitemap.xml') ?>" target="_blank" rel="noopener"><?= esc(lang('Seo.Admin.sitemap')) ?></a></p>
<h3 id="redirects"><?= esc(lang('Seo.Admin.redirects')) ?></h3>
<p class="text-secondary"><?= esc(lang('Seo.Admin.redirectHint')) ?></p>
<div class="card mb-3" id="redirect-form">
    <div class="card-header"><h4 class="card-title"><?= esc(lang($editing === null ? 'Seo.Admin.createRedirect' : 'Seo.Admin.editRedirect')) ?></h4></div>
    <form method="post" action="<?= site_url('admin/seo/save') ?>" class="card-body">
        <?= csrf_field() ?><input type="hidden" name="original" value="<?= esc($editing['source_path'] ?? '', 'attr') ?>">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="redirect-source"><?= esc(lang('Seo.Admin.source')) ?></label>
                <input class="form-control" id="redirect-source" name="source" value="<?= esc(old('source', $editing['source_path'] ?? '')) ?>" placeholder="/old-contact" maxlength="500" required></div>
            <div class="col-md-6"><label class="form-label" for="redirect-target"><?= esc(lang('Seo.Admin.target')) ?></label>
                <input class="form-control" id="redirect-target" name="target" value="<?= esc(old('target', $editTarget)) ?>" placeholder="/contact" maxlength="500" required></div>
        </div>
        <p class="form-text mt-2"><?= esc(lang('Seo.Admin.manualHint')) ?></p>
        <button class="btn btn-primary"><?= esc(lang('MCMS.common.save')) ?></button>
        <?php if ($editing !== null): ?><a class="btn btn-outline-secondary" href="<?= site_url('admin/seo') ?>#redirects"><?= esc(lang('MCMS.common.cancel')) ?></a><?php endif ?>
    </form>
</div>
<div class="card"><div class="table-responsive"><table class="table card-table table-vcenter">
    <thead><tr><th><?= esc(lang('Seo.Admin.source')) ?></th><th><?= esc(lang('Seo.Admin.target')) ?></th><th></th></tr></thead><tbody>
    <?php if ($redirects === []): ?><tr><td colspan="3"><?= esc(lang('Seo.Admin.empty')) ?></td></tr><?php endif ?>
    <?php foreach ($redirects as $redirect): ?>
        <tr><td><code><?= esc($redirect['source_path']) ?></code></td><td><?= esc($repository->destination($redirect) ?? lang('Seo.Admin.unavailable')) ?></td>
            <td><div class="d-flex gap-2 flex-wrap"><a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/seo') . '?edit=' . rawurlencode($redirect['source_path']) ?>#redirect-form"><?= esc(lang('MCMS.common.edit')) ?></a>
            <form method="post" action="<?= site_url('admin/seo/delete') ?>" onsubmit="return confirm(<?= esc(json_encode(lang('Seo.Admin.deleteConfirm')), 'attr') ?>);">
                <?= csrf_field() ?><input type="hidden" name="source" value="<?= esc($redirect['source_path'], 'attr') ?>"><button class="btn btn-outline-danger btn-sm"><?= esc(lang('MCMS.common.delete')) ?></button>
            </form></div></td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table></div></div>
<?php if ($lastPage > 1): ?><nav class="d-flex justify-content-between mt-3" aria-label="<?= esc(lang('Seo.Admin.title'), 'attr') ?>">
    <div><?php if ($page > 1): ?><a href="<?= site_url('admin/seo') . '?page=' . ($page - 1) ?>"><?= esc(lang('MCMS.common.previousPage')) ?></a><?php endif ?></div><span><?= (int) $page ?> / <?= (int) $lastPage ?></span><div><?php if ($page < $lastPage): ?><a href="<?= site_url('admin/seo') . '?page=' . ($page + 1) ?>"><?= esc(lang('MCMS.common.nextPage')) ?></a><?php endif ?></div>
</nav><?php endif ?>
<?= $this->endSection() ?>
