<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle"><?= esc(lang('MCMS.common.system')) ?></div>
            <h2 class="page-title"><?= esc(lang('MCMS.modules.title')) ?></h2>
        </div>
        <?php if ($maintenance === null): ?>
        <div class="col-auto">
            <a class="btn btn-primary" href="<?= site_url('admin/modules/package') ?>"><i class="ti ti-upload me-2" aria-hidden="true"></i><?= esc(lang('MCMS.modules.installPackage')) ?></a>
        </div>
        <?php endif ?>
    </div>
</div>

<?php if ($maintenance !== null): ?>
<div class="alert alert-warning"><strong><?= esc(lang('MCMS.modules.maintenance')) ?></strong>
<a href="<?= site_url('admin/modules/' . rawurlencode($maintenance['id']) . '/update') ?>"><?= esc(lang('MCMS.modules.openRecovery')) ?></a></div>
<?php endif ?>
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
                    $moduleName = $maintenance === null && str_starts_with($module->name, '@')
                        ? lang(substr($module->name, 1))
                        : $module->name;
                    $description = $maintenance === null && str_starts_with($module->description, '@')
                        ? lang(substr($module->description, 1))
                        : $module->description;
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
                                : (isset($states[$id]) ? lang('MCMS.modules.disabled')
                                    : lang(str_starts_with($module->path, WRITEPATH . 'modules-data/installed/')
                                        ? 'MCMS.modules.packageReady' : 'MCMS.modules.notInstalled'))) ?>
                        </span>
                    </td>
                    <td><?= esc(implode(', ', array_map(static fn ($d) => is_array($d) ? ($d['id'] ?? '?') : $d, $module->requires)) ?: '—') ?></td>
                    <td>
                        <div class="d-flex gap-1">
                        <?php if (str_starts_with($module->path, WRITEPATH . 'modules-data/installed/')): ?>
                            <a class="btn btn-outline-secondary btn-sm btn-icon admin-icon-action" href="<?= site_url('admin/modules/' . rawurlencode($id) . '/update') ?>" aria-label="<?= esc(lang('MCMS.modules.update'), 'attr') ?>" title="<?= esc(lang('MCMS.modules.update'), 'attr') ?>" data-bs-toggle="tooltip" data-bs-container="body"><i class="ti ti-refresh" aria-hidden="true"></i></a>
                        <?php endif ?>
                        <?php if ($maintenance === null): ?>
                        <?php if ($enabled): ?>
                            <form method="post" action="<?= site_url('admin/modules/' . rawurlencode($id) . '/disable') ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-outline-secondary btn-sm btn-icon admin-icon-action" type="submit" aria-label="<?= esc(lang('MCMS.common.disable'), 'attr') ?>" title="<?= esc(lang('MCMS.common.disable'), 'attr') ?>" data-bs-toggle="tooltip" data-bs-container="body"><i class="ti ti-player-pause" aria-hidden="true"></i></button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= site_url('admin/modules/' . rawurlencode($id) . '/enable') ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-primary btn-sm btn-icon admin-icon-action" type="submit" aria-label="<?= esc(lang('MCMS.common.enable'), 'attr') ?>" title="<?= esc(lang('MCMS.common.enable'), 'attr') ?>" data-bs-toggle="tooltip" data-bs-container="body"><i class="ti ti-player-play" aria-hidden="true"></i></button>
                            </form>
                        <?php endif ?>
                        <?php endif ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
