<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle"><?= esc(lang('MCMS.common.system')) ?></div>
            <h2 class="page-title"><?= esc(lang('MCMS.modules.title')) ?></h2>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
            <tr>
                <th><?= esc(lang('MCMS.modules.module')) ?></th>
                <th><?= esc(lang('MCMS.common.version')) ?></th>
                <th><?= esc(lang('MCMS.common.status')) ?></th>
                <th><?= esc(lang('MCMS.modules.dependencies')) ?></th>
                <th class="w-1"></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($modules === []): ?>
                <tr>
                    <td colspan="5" class="text-secondary"><?= esc(lang('MCMS.modules.none')) ?></td>
                </tr>
            <?php endif ?>

            <?php foreach ($modules as $id => $module): ?>
                <?php
                    $enabled = (int) ($states[$id]['enabled'] ?? 0) === 1;
                    $moduleName = $module->name;
                    $description = $module->description;
                    if ($id === 'media') {
                        $moduleName = lang('MCMS.media.title');
                        $description = lang('MCMS.modules.mediaDescription');
                    }
                ?>
                <tr>
                    <td>
                        <div class="fw-bold"><?= esc($moduleName) ?></div>
                        <div class="text-secondary"><?= esc($description) ?></div>
                        <code><?= esc($id) ?></code>
                    </td>
                    <td><?= esc($module->version) ?></td>
                    <td>
                        <span class="badge <?= $enabled ? 'bg-success-lt' : 'bg-secondary-lt' ?>">
                            <?= esc($enabled
                                ? lang('MCMS.modules.enabled')
                                : (isset($states[$id]) ? lang('MCMS.modules.disabled') : lang('MCMS.modules.notInstalled'))) ?>
                        </span>
                    </td>
                    <td><?= esc(implode(', ', array_map(static fn ($d) => is_array($d) ? ($d['id'] ?? '?') : $d, $module->requires)) ?: '—') ?></td>
                    <td>
                        <?php if ($enabled): ?>
                            <form method="post" action="<?= site_url('admin/modules/' . rawurlencode($id) . '/disable') ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-outline-secondary btn-sm" type="submit"><?= esc(lang('MCMS.common.disable')) ?></button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= site_url('admin/modules/' . rawurlencode($id) . '/enable') ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-primary btn-sm" type="submit"><?= esc(lang('MCMS.common.enable')) ?></button>
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
