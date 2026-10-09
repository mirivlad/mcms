<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col"><h2 class="page-title"><?= esc(lang('MCMS.modules.update')) ?>: <code><?= esc($id) ?></code></h2></div>
        <div class="col-auto"><a class="btn btn-outline-secondary" href="<?= site_url('admin/modules') ?>"><?= esc(lang('MCMS.modules.title')) ?></a></div>
    </div>
</div>
<?php if ($state !== null): ?>
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
            <strong><?= esc($state['from']) ?> → <?= esc($state['to']) ?></strong>
            <span class="badge bg-secondary-lt"><?= esc(lang('MCMS.modules.updateStatus.' . $state['status'])) ?></span>
            <a class="btn btn-outline-secondary btn-sm" href="<?= site_url('admin/modules/' . rawurlencode($id) . '/update/snapshot') ?>"><i class="ti ti-download me-2" aria-hidden="true"></i><?= esc(lang('MCMS.modules.previousCode')) ?></a>
        </div>
        <?php if ($state['error'] !== null): ?><div class="alert alert-danger text-break"><?= esc($state['error']) ?></div><?php endif ?>
        <?php if ($state['status'] === 'queued' && $maintenance === null): ?>
        <p><?= esc(lang('MCMS.modules.backupHint')) ?></p>
        <form method="post" action="<?= site_url('admin/modules/' . rawurlencode($id) . '/update/apply') ?>">
            <?= csrf_field() ?><input type="hidden" name="operation" value="<?= esc($state['operation'], 'attr') ?>">
            <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="backup" value="1" required><span class="form-check-label"><?= esc(lang('MCMS.modules.backupConfirm')) ?></span></label>
            <button class="btn btn-primary" type="submit"><?= esc(lang('MCMS.modules.applyUpdate')) ?></button>
        </form>
        <form class="mt-2" method="post" action="<?= site_url('admin/modules/' . rawurlencode($id) . '/update/cancel') ?>">
            <?= csrf_field() ?><input type="hidden" name="operation" value="<?= esc($state['operation'], 'attr') ?>">
            <button class="btn btn-outline-secondary" type="submit"><?= esc(lang('MCMS.common.cancel')) ?></button>
        </form>
        <?php elseif ($maintenance !== null && in_array($state['status'], ['queued', 'failed', 'applying', 'restoring'], true)): ?>
        <div class="alert alert-warning"><?= esc(lang('MCMS.modules.recoveryHint')) ?></div>
        <form method="post" action="<?= site_url('admin/modules/' . rawurlencode($id) . '/update/restore') ?>">
            <?= csrf_field() ?><input type="hidden" name="operation" value="<?= esc($state['operation'], 'attr') ?>">
            <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="database_restored" value="1" required><span class="form-check-label"><?= esc(lang('MCMS.modules.restoreConfirm')) ?></span></label>
            <button class="btn btn-warning" type="submit"><?= esc(lang('MCMS.modules.restoreCode')) ?></button>
        </form>
        <?php else: ?>
        <p class="mb-0"><?= esc(lang('MCMS.modules.enableAfterUpdate')) ?></p>
        <?php endif ?>
    </div>
</div>
<?php endif ?>
<?php if ($maintenance === null && ($state === null || in_array($state['status'], ['applied', 'restored'], true))): ?>
<div class="card mb-3">
    <div class="card-body">
        <p class="text-secondary"><?= esc(lang('MCMS.modules.updateHint')) ?></p>
        <form method="post" enctype="multipart/form-data" action="<?= site_url('admin/modules/' . rawurlencode($id) . '/update') ?>">
            <?= csrf_field() ?>
            <label class="form-label" for="module-update"><?= esc(lang('MCMS.modules.archive')) ?></label>
            <input class="form-control mb-3" id="module-update" name="package" type="file" accept=".zip,application/zip" required>
            <button class="btn btn-primary" type="submit"><?= esc(lang('MCMS.modules.inspectPackage')) ?></button>
        </form>
    </div>
</div>
<?php if ($preview !== null): ?>
<div class="card">
    <div class="card-header"><h3 class="card-title"><?= esc(lang('MCMS.modules.packagePreview')) ?></h3></div>
    <div class="card-body">
        <dl class="row mb-3">
            <dt class="col-sm-4"><?= esc(lang('MCMS.common.version')) ?></dt><dd class="col-sm-8"><?= esc($module?->version ?? '—') ?> → <?= esc($preview['version']) ?></dd>
            <dt class="col-sm-4"><?= esc(lang('MCMS.modules.coreConstraint')) ?></dt><dd class="col-sm-8 text-break"><?= esc($preview['mcms']) ?></dd>
            <dt class="col-sm-4"><?= esc(lang('MCMS.modules.dependencies')) ?></dt><dd class="col-sm-8 text-break"><?= esc(implode(', ', array_map(static fn ($d) => is_array($d) ? $d['id'] . ' ' . ($d['version'] ?? '*') : $d, $preview['requires'])) ?: '—') ?></dd>
            <dt class="col-sm-4"><?= esc(lang('MCMS.modules.packageContents')) ?></dt><dd class="col-sm-8"><?= esc(sprintf(lang('MCMS.modules.packageSize'), $preview['files'], number_format($preview['bytes'] / 1024, 1))) ?></dd>
            <dt class="col-sm-4">SHA-256</dt><dd class="col-sm-8"><code class="text-break"><?= esc($preview['sha256']) ?></code></dd>
        </dl>
        <form method="post" action="<?= site_url('admin/modules/' . rawurlencode($id) . '/update/queue') ?>">
            <?= csrf_field() ?><input type="hidden" name="token" value="<?= esc($preview['token'], 'attr') ?>">
            <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="trusted" value="1" required><span class="form-check-label"><?= esc(lang('MCMS.modules.trustPackage')) ?></span></label>
            <button class="btn btn-primary" type="submit"><?= esc(lang('MCMS.modules.prepareUpdate')) ?></button>
        </form>
    </div>
</div>
<?php endif ?>
<?php endif ?>
<?= $this->endSection() ?>
