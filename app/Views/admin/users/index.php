<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$pageUrl = static function (int $target) use ($query): string {
    $params = [];
    if ($query !== '') {
        $params['q'] = $query;
    }
    if ($target > 1) {
        $params['page'] = $target;
    }
    return site_url('admin/users') . ($params ? '?' . http_build_query($params) : '');
};
?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md">
            <div class="page-pretitle"><?= esc(lang('MCMS.common.core')) ?></div>
            <h2 class="page-title"><?= esc(lang('MCMS.users.title')) ?></h2>
            <div class="text-secondary mt-1"><?= esc(sprintf(lang('MCMS.users.count'), $count)) ?></div>
        </div>
        <div class="col-12 col-md-auto ms-md-auto">
            <a class="btn btn-primary" href="<?= site_url('admin/users/create') ?>"><?= esc(lang('MCMS.users.create')) ?></a>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= site_url('admin/users') ?>" class="row g-2">
            <div class="col">
                <input class="form-control" type="search" name="q" maxlength="120"
                    value="<?= esc($query, 'attr') ?>"
                    placeholder="<?= esc(lang('MCMS.users.searchPlaceholder'), 'attr') ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-outline-primary" type="submit"><?= esc(lang('MCMS.common.search')) ?></button>
            </div>
            <?php if ($query !== ''): ?>
                <div class="col-auto">
                    <a class="btn btn-link" href="<?= site_url('admin/users') ?>"><?= esc(lang('MCMS.common.clear')) ?></a>
                </div>
            <?php endif ?>
        </form>
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
            <?php if ($users === []): ?>
                <tr><td colspan="<?= $canAccess ? 6 : 5 ?>" class="text-secondary"><?= esc(lang('MCMS.users.empty')) ?></td></tr>
            <?php endif ?>
            <?php foreach ($users as $entry): ?>
                <tr>
                    <td><?= esc((string) $entry->id) ?></td>
                    <td><?= esc($entry->username ?? '—') ?></td>
                    <td><?= esc($entry->email ?? '—') ?></td>
                    <td><?= esc(implode(', ', $entry->getGroups() ?? []) ?: '—') ?></td>
                    <td><?= esc(implode(', ', $entry->getPermissions() ?? []) ?: '—') ?></td>
                    <?php if ($canAccess): ?>
                        <td>
                            <a class="btn btn-outline-primary btn-sm btn-icon admin-icon-action" href="<?= site_url('admin/users/' . $entry->id . '/access') ?>" aria-label="<?= esc(lang('MCMS.users.access'), 'attr') ?>" title="<?= esc(lang('MCMS.users.access'), 'attr') ?>" data-bs-toggle="tooltip" data-bs-container="body"><i class="ti ti-user-shield" aria-hidden="true"></i></a>
                        </td>
                    <?php endif ?>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?php if ($lastPage > 1): ?>
    <nav class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3"
        aria-label="<?= esc(lang('MCMS.users.title'), 'attr') ?>">
        <div>
            <?php if ($page > 1): ?>
                <a class="btn btn-outline-secondary" href="<?= esc($pageUrl($page - 1), 'attr') ?>"><?= esc(lang('MCMS.common.previousPage')) ?></a>
            <?php endif ?>
        </div>
        <div class="text-secondary small"><?= (int) $page ?> / <?= (int) $lastPage ?></div>
        <div>
            <?php if ($page < $lastPage): ?>
                <a class="btn btn-outline-secondary" href="<?= esc($pageUrl($page + 1), 'attr') ?>"><?= esc(lang('MCMS.common.nextPage')) ?></a>
            <?php endif ?>
        </div>
    </nav>
<?php endif ?>
<?= $this->endSection() ?>