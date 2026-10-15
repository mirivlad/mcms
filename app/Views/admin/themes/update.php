<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="page-header mb-4"><div class="row g-2 align-items-center"><div class="col"><h2 class="page-title"><?= esc(lang('MCMS.themes.update')) ?> · <?= esc($theme->name) ?></h2></div><div class="col-auto"><a class="btn btn-outline-secondary" href="<?= $settingsUrl('admin/themes') ?>"><?= esc(lang('MCMS.themes.title')) ?></a></div></div></div>
<div class="card mb-3"><div class="card-body">
    <p class="text-secondary"><?= esc(lang('MCMS.themes.updateHint')) ?></p>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <label class="form-label" for="theme-update-zip"><?= esc(lang('MCMS.themes.archive')) ?></label>
        <input class="form-control mb-3" id="theme-update-zip" name="package" type="file" accept=".zip,application/zip" required>
        <button class="btn btn-primary" type="submit"><?= esc(lang('MCMS.themes.inspectPackage')) ?></button>
    </form>
</div></div>
<?php if ($preview !== null): ?>
<div class="card mb-3"><div class="card-header"><h3 class="card-title"><?= esc(lang('MCMS.themes.updatePreview')) ?></h3></div><div class="card-body">
    <p class="text-break"><code><?= esc($theme->id) ?></code> · <?= esc($preview['current']) ?> → <strong><?= esc($preview['version']) ?></strong></p>
    <p><?= esc($preview['description']) ?></p>
    <p class="text-break"><small>SHA-256: <?= esc($preview['sha256']) ?></small></p>
    <form method="post" action="<?= $settingsUrl('admin/themes/' . $theme->id . '/update/apply') ?>">
        <?= csrf_field() ?><input type="hidden" name="token" value="<?= esc($preview['token'], 'attr') ?>">
        <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="trusted" value="1" required><span class="form-check-label"><?= esc(lang('MCMS.themes.updateTrust')) ?></span></label>
        <button class="btn btn-primary" type="submit"><?= esc(lang('MCMS.themes.update')) ?></button>
    </form>
</div></div>
<?php endif ?>
<?php if ($previous !== null): ?>
<div class="card"><div class="card-header"><h3 class="card-title"><?= esc(lang('MCMS.themes.rollback')) ?> · <?= esc($previous['version']) ?></h3></div><div class="card-body">
    <form method="post" action="<?= $settingsUrl('admin/themes/' . $theme->id . '/rollback') ?>">
        <?= csrf_field() ?><input type="hidden" name="source" value="<?= esc($source, 'attr') ?>">
        <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="trusted" value="1" required><span class="form-check-label"><?= esc(lang('MCMS.themes.rollbackTrust')) ?></span></label>
        <button class="btn btn-outline-warning" type="submit"><?= esc(lang('MCMS.themes.rollback')) ?></button>
    </form>
</div></div>
<?php endif ?>
<?= $this->endSection() ?>
