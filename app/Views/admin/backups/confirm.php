<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="page-header mb-4">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle"><?= esc(lang('MCMS.backups.title')) ?></div>
            <h2 class="page-title"><?= esc(lang('MCMS.backups.restoreTitle')) ?></h2>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-body">
        <div class="alert alert-warning">
            <i class="ti ti-alert-triangle me-2"></i><?= esc(lang('MCMS.backups.restoreWarning')) ?>
        </div>
        <dl class="row">
            <dt class="col-sm-4"><?= esc(lang('MCMS.backups.filename')) ?></dt>
            <dd class="col-sm-8"><?= esc($state['label']) ?></dd>
            <dt class="col-sm-4"><?= esc(lang('MCMS.backups.created')) ?></dt>
            <dd class="col-sm-8"><?= esc($state['created_at']) ?></dd>
            <dt class="col-sm-4"><?= esc(lang('MCMS.backups.archiveVersion')) ?></dt>
            <dd class="col-sm-8"><?= esc($state['app_version']) ?></dd>
            <dt class="col-sm-4"><?= esc(lang('MCMS.backups.installedVersion')) ?></dt>
            <dd class="col-sm-8"><?= esc($currentVersion) ?></dd>
            <dt class="col-sm-4"><?= esc(lang('MCMS.backups.files')) ?></dt>
            <dd class="col-sm-8"><?= esc((string) $state['files']) ?></dd>
            <dt class="col-sm-4"><?= esc(lang('MCMS.backups.size')) ?></dt>
            <dd class="col-sm-8"><?= esc(number_format($state['bytes'] / 1048576, 2, '.', ' ')) ?> MiB</dd>
            <dt class="col-sm-4">SHA-256</dt>
            <dd class="col-sm-8 text-break"><?= esc($state['sha256']) ?></dd>
        </dl>
        <?php if (! $versionCompatible): ?>
            <div class="alert alert-danger" role="alert">
                <i class="ti ti-alert-circle me-2"></i>
                <?= esc(sprintf(lang('MCMS.backups.versionMismatch'), $state['app_version'], $currentVersion, $state['app_version'])) ?>
            </div>
            <a href="<?= site_url('admin/backups') ?>" class="btn btn-outline-secondary">
                <?= esc(lang('MCMS.common.cancel')) ?>
            </a>
        <?php else: ?>
        <form method="post" action="<?= site_url('admin/backups/restore/apply') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= esc($state['token'], 'attr') ?>">
            <label class="form-check mb-3">
                <input type="checkbox" name="acknowledge" value="1" class="form-check-input" required>
                <span class="form-check-label"><?= esc(lang('MCMS.backups.acknowledge')) ?></span>
            </label>
            <button type="submit" class="btn btn-danger"><?= esc(lang('MCMS.backups.startRestore')) ?></button>
            <a href="<?= site_url('admin/backups') ?>" class="btn btn-link"><?= esc(lang('MCMS.common.cancel')) ?></a>
        </form>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
