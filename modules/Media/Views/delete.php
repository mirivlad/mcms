<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<h2 class="page-title mb-3"><?= esc(lang('MCMS.media.confirmUsedDelete')) ?></h2>
<div class="card"><div class="card-body">
    <p><?= esc($item['original_name'] ?? '') ?></p>
    <div class="alert alert-warning"><?= esc(lang('MCMS.media.usedDeleteWarning')) ?></div>
    <ul>
        <?php foreach ($usages as $usage): ?>
            <li><a href="<?= esc(site_url(ltrim($usage['edit_url'], '/')), 'attr') ?>"><?= esc($usage['label']) ?></a> <span class="text-secondary">(<?= esc($usage['owner']) ?>)</span></li>
        <?php endforeach ?>
    </ul>
    <form method="post" action="<?= site_url('admin/media/' . (int) $item['id'] . '/delete') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="usage_token" value="<?= esc($usageToken, 'attr') ?>">
        <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirm" value="1" required><span class="form-check-label"><?= esc(lang('MCMS.media.acceptBrokenLinks')) ?></span></label>
        <button class="btn btn-danger" type="submit"><?= esc(lang('MCMS.common.delete')) ?></button>
        <a class="btn btn-outline-secondary" href="<?= site_url('admin/media') ?>"><?= esc(lang('MCMS.common.back')) ?></a>
    </form>
</div></div>
<?= $this->endSection() ?>
