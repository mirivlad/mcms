<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">System</div>
            <h2 class="page-title">Modules</h2>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
            <tr>
                <th>Module</th>
                <th>Version</th>
                <th>Status</th>
                <th>Dependencies</th>
                <th class="w-1"></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($modules === []): ?>
                <tr>
                    <td colspan="5" class="text-secondary">No modules are installed on disk.</td>
                </tr>
            <?php endif ?>

            <?php foreach ($modules as $id => $module): ?>
                <?php $enabled = (int) ($states[$id]['enabled'] ?? 0) === 1; ?>
                <tr>
                    <td>
                        <div class="fw-bold"><?= esc($module->name) ?></div>
                        <div class="text-secondary"><?= esc($module->description) ?></div>
                        <code><?= esc($id) ?></code>
                    </td>
                    <td><?= esc($module->version) ?></td>
                    <td>
                        <span class="badge <?= $enabled ? 'bg-success-lt' : 'bg-secondary-lt' ?>">
                            <?= $enabled ? 'Enabled' : (isset($states[$id]) ? 'Disabled' : 'Not installed') ?>
                        </span>
                    </td>
                    <td><?= esc(implode(', ', array_map(static fn ($d) => is_array($d) ? ($d['id'] ?? '?') : $d, $module->requires)) ?: '—') ?></td>
                    <td>
                        <?php if ($enabled): ?>
                            <form method="post" action="<?= site_url('admin/modules/' . rawurlencode($id) . '/disable') ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-outline-secondary btn-sm" type="submit">Disable</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= site_url('admin/modules/' . rawurlencode($id) . '/enable') ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-primary btn-sm" type="submit">Enable</button>
                            </form>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
