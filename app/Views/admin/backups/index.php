<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle"><?= esc(lang('MCMS.common.core')) ?></div>
            <h2 class="page-title"><?= esc(lang('MCMS.backups.title')) ?></h2>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h3 class="card-title"><?= esc(lang('MCMS.backups.createTitle')) ?></h3>
        <p class="text-secondary"><?= esc(lang('MCMS.backups.description')) ?></p>
        <form method="post" action="<?= site_url('admin/backups/create') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary">
                <i class="ti ti-database-export me-1"></i><?= esc(lang('MCMS.backups.create')) ?>
            </button>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h3 class="card-title"><?= esc(lang('MCMS.backups.restoreTitle')) ?></h3>
        <p class="text-secondary"><?= esc(lang('MCMS.backups.restoreIntro')) ?></p>
        <form method="post" action="<?= site_url('admin/backups/restore/upload') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label class="form-label" for="restore-file"><?= esc(lang('MCMS.backups.chooseZip')) ?></label>
                    <input class="form-control" id="restore-file" name="archive" type="file" accept=".zip,application/zip" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="ti ti-file-search me-1"></i><?= esc(lang('MCMS.backups.inspect')) ?>
                    </button>
                </div>
            </div>
        </form>
        <?php if ($lastRestore !== null): ?>
            <div class="alert alert-info mt-3 mb-0">
                <?= esc(lang('MCMS.backups.lastRestore')) ?>:
                <?= esc(lang('MCMS.backups.state.' . $lastRestore['status'])) ?>
                <?php if ($lastRestore['safety'] !== null): ?>
                    — <?= esc(lang('MCMS.backups.safety')) ?>:
                    <?= esc($lastRestore['safety']) ?>
                <?php endif ?>
                <?php if ($lastRestore['error'] !== null): ?>
                    — <?= esc($lastRestore['error']) ?>
                <?php endif ?>
            </div>
        <?php endif ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title"><?= esc(lang('MCMS.backups.available')) ?></h3></div>
    <?php if ($items === []): ?>
        <div class="card-body text-secondary"><?= esc(lang('MCMS.backups.empty')) ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead><tr>
                    <th><?= esc(lang('MCMS.backups.filename')) ?></th>
                    <th><?= esc(lang('MCMS.backups.size')) ?></th>
                    <th><?= esc(lang('MCMS.backups.created')) ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= esc($item['name']) ?></td>
                        <td><?= esc(number_format($item['size'] / 1048576, 2, '.', ' ')) ?> MiB</td>
                        <td><?= esc(date('Y-m-d H:i:s', $item['created_at'])) ?></td>
                        <td class="text-end">
                            <div class="d-flex flex-wrap gap-2 justify-content-end">
                            <form method="post" action="<?= site_url('admin/backups/' . rawurlencode($item['name']) . '/restore') ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-outline-warning btn-sm">
                                    <i class="ti ti-restore me-1"></i><?= esc(lang('MCMS.backups.restore')) ?>
                                </button>
                            </form>
                            <a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/backups/' . rawurlencode($item['name']) . '/download') ?>">
                                <i class="ti ti-download me-1"></i><?= esc(lang('MCMS.backups.download')) ?>
                            </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
