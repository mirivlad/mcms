<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col"><h2 class="page-title"><?= esc(lang('MCMS.modules.installPackage')) ?></h2></div>
        <div class="col-auto"><a class="btn btn-outline-secondary" href="<?= site_url('admin/modules') ?>"><?= esc(lang('MCMS.modules.title')) ?></a></div>
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <form method="post" enctype="multipart/form-data" action="<?= site_url('admin/modules/package') ?>">
            <?= csrf_field() ?>
            <label class="form-label" for="module-package"><?= esc(lang('MCMS.modules.archive')) ?></label>
            <input class="form-control" id="module-package" name="package" type="file" accept=".zip,application/zip" required>
            <div class="form-hint mb-3"><?= esc(lang('MCMS.modules.archiveHint')) ?></div>
            <button class="btn btn-primary" type="submit"><?= esc(lang('MCMS.modules.inspectPackage')) ?></button>
        </form>
    </div>
</div>
<?php if ($preview !== null): ?>
<div class="card">
    <div class="card-header"><h3 class="card-title"><?= esc(lang('MCMS.modules.packagePreview')) ?></h3></div>
    <div class="card-body">
        <dl class="row mb-3">
            <dt class="col-sm-4"><?= esc(lang('MCMS.modules.module')) ?></dt><dd class="col-sm-8 text-break"><?= esc($preview['name']) ?> <code><?= esc($preview['id']) ?></code></dd>
            <dt class="col-sm-4"><?= esc(lang('MCMS.common.version')) ?></dt><dd class="col-sm-8 text-break"><?= esc($preview['version']) ?></dd>
            <dt class="col-sm-4"><?= esc(lang('MCMS.modules.coreConstraint')) ?></dt><dd class="col-sm-8 text-break"><?= esc($preview['mcms']) ?></dd>
            <dt class="col-sm-4"><?= esc(lang('MCMS.modules.dependencies')) ?></dt><dd class="col-sm-8 text-break"><?= esc(implode(', ', array_map(static fn ($d) => is_array($d) ? $d['id'] . ' ' . ($d['version'] ?? '*') : $d, $preview['requires'])) ?: '—') ?></dd>
            <dt class="col-sm-4"><?= esc(lang('MCMS.modules.packageContents')) ?></dt><dd class="col-sm-8"><?= esc(sprintf(lang('MCMS.modules.packageSize'), $preview['files'], number_format($preview['bytes'] / 1024, 1))) ?></dd>
            <dt class="col-sm-4">SHA-256</dt><dd class="col-sm-8"><code class="text-break"><?= esc($preview['sha256']) ?></code></dd>
        </dl>
        <p class="text-secondary"><?= esc(lang('MCMS.modules.enableSeparately')) ?></p>
        <form method="post" action="<?= site_url('admin/modules/package/install') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= esc($preview['token'], 'attr') ?>">
            <label class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="trusted" value="1" required>
                <span class="form-check-label"><?= esc(lang('MCMS.modules.trustPackage')) ?></span>
            </label>
            <button class="btn btn-primary" type="submit"><?= esc(lang('MCMS.modules.addPackage')) ?></button>
        </form>
        <form class="mt-2" method="post" action="<?= site_url('admin/modules/package/cancel') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-outline-secondary" type="submit"><?= esc(lang('MCMS.common.cancel')) ?></button>
        </form>
    </div>
</div>
<?php endif ?>
<?= $this->endSection() ?>
