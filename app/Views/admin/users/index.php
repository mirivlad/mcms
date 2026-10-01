<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md">
            <div class="page-pretitle"><?= esc(lang('MCMS.common.core')) ?></div>
            <h2 class="page-title"><?= esc(lang('MCMS.users.title')) ?></h2>
        </div>
        <div class="col-12 col-md-auto ms-md-auto">
            <a class="btn btn-primary" href="<?= site_url('admin/users/create') ?>"><?= esc(lang('MCMS.users.create')) ?></a>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
            <tr>
                <th>ID</th>
                <th><?= esc(lang('MCMS.common.user')) ?></th>
                <th><?= esc(lang('MCMS.users.email')) ?></th>
                <th><?= esc(lang('MCMS.users.groups')) ?></th>
                <th><?= esc(lang('MCMS.users.directPermissions')) ?></th>
                <?php if ($canAccess): ?><th class="w-1"></th><?php endif ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $entry): ?>
                <tr>
                    <td><?= esc((string) $entry->id) ?></td>
                    <td><?= esc($entry->username ?? '—') ?></td>
                    <td><?= esc($entry->email ?? '—') ?></td>
                    <td><?= esc(implode(', ', $entry->getGroups() ?? []) ?: '—') ?></td>
                    <td><?= esc(implode(', ', $entry->getPermissions() ?? []) ?: '—') ?></td>
                    <?php if ($canAccess): ?>
                        <td>
                            <a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/users/' . $entry->id . '/access') ?>">
                                <?= esc(lang('MCMS.users.access')) ?>
                            </a>
                        </td>
                    <?php endif ?>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>