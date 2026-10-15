<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<h2><?= esc(lang('Extensions.remove')) ?>: <?= esc($manifest->name) ?> <?= esc($manifest->version) ?></h2>
<div class="card"><div class="card-body">
<p><?= esc(lang('Extensions.keepHint')) ?></p>
<p><?= esc(lang('Extensions.purgeHint')) ?></p>
<dl>
<?php foreach ($resources as $label => $value): ?>
    <dt><?= esc(lang('Extensions.' . $label)) ?></dt>
    <dd class="text-break"><?= esc(is_array($value) ? (implode(', ', $value) ?: '—') : $value) ?></dd>
<?php endforeach ?>
</dl>
<?php if ($blockers !== []): ?>
    <div class="alert alert-danger"><?= esc(lang('Extensions.blocked')) ?> <?= esc(implode(', ', $blockers)) ?></div>
<?php else: ?>
    <?php if ($purgeError !== null): ?><div class="alert alert-warning"><?= esc($purgeError) ?></div><?php endif ?>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="fingerprint" value="<?= esc($fingerprint, 'attr') ?>">
        <label class="form-check mb-2"><input class="form-check-input" type="radio" name="mode" value="keep" checked><span class="form-check-label"><?= esc(lang('Extensions.keep')) ?></span></label>
        <label class="form-check mb-3"><input class="form-check-input" type="radio" name="mode" value="purge" <?= $purgeError !== null ? 'disabled' : '' ?>><span class="form-check-label"><?= esc(lang('Extensions.purge')) ?></span></label>
        <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="backup" value="1"><span class="form-check-label"><?= esc(lang('Extensions.backupRequired')) ?> <a href="<?= site_url('admin/backups') ?>"><?= esc(lang('MCMS.nav.backups')) ?></a></span></label>
        <label class="form-label" for="confirm-id"><?= esc(lang('Extensions.typeId')) ?> <code><?= esc($id) ?></code></label>
        <input class="form-control mb-3" id="confirm-id" name="confirm" required autocomplete="off">
        <button type="submit" class="btn btn-danger"><?= esc(lang('Extensions.remove')) ?></button>
    </form>
<?php endif ?>
</div></div>
<?= $this->endSection() ?>
