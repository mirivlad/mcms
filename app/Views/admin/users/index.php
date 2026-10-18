<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$params = array_filter([
    'q' => $query, 'username' => $usernameFilter, 'email' => $emailFilter,
    'group' => $groupFilter, 'per_page' => $perPage !== 25 ? $perPage : null,
], static fn ($v): bool => $v !== '' && $v !== null);
$pageUrl = static function (int $target) use ($params): string {
    $values = $params;
    if ($target > 1) $values['page'] = $target;
    return site_url('admin/users') . ($values ? '?' . http_build_query($values) : '');
};
?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md">
            <div class="page-pretitle"><?= esc(lang('MCMS.common.core')) ?></div>
            <h2 class="page-title"><?= esc(lang('MCMS.users.title')) ?></h2>
        </div>
        <div class="col-12 col-md-auto">
            <a class="btn btn-primary" href="<?= site_url('admin/users/create') ?>"><?= esc(lang('MCMS.users.create')) ?></a>
        </div>
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <form id="users-filters" method="get" action="<?= site_url('admin/users') ?>" class="row g-2" role="search">
            <div class="col-lg-3 col-sm-6">
                <label for="users-q" class="form-label"><?= esc(lang('MCMS.common.search')) ?></label>
                <input id="users-q" type="search" class="form-control" name="q" maxlength="120"
                    value="<?= esc($query, 'attr') ?>" placeholder="<?= esc(lang('MCMS.users.searchPlaceholder'), 'attr') ?>">
            </div>
            <div class="col-lg-2 col-sm-6">
                <label for="users-username" class="form-label"><?= esc(lang('MCMS.users.username')) ?></label>
                <input id="users-username" type="search" class="form-control" name="username" maxlength="120"
                    value="<?= esc($usernameFilter, 'attr') ?>">
            </div>
            <div class="col-lg-3 col-sm-6">
                <label for="users-email" class="form-label"><?= esc(lang('MCMS.users.email')) ?></label>
                <input id="users-email" type="search" class="form-control" name="email" maxlength="120"
                    value="<?= esc($emailFilter, 'attr') ?>">
            </div>
            <div class="col-lg-2 col-sm-6">
                <label for="users-group" class="form-label"><?= esc(lang('MCMS.users.groups')) ?></label>
                <select id="users-group" name="group" class="form-select">
                    <option value=""><?= esc(lang('MCMS.users.allGroups')) ?></option>
                    <?php foreach ($groupOptions as $groupId): ?>
                        <option value="<?= esc($groupId, 'attr') ?>" <?= $groupFilter === $groupId ? 'selected' : '' ?>><?= esc($groupId) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-lg-2 col-sm-6">
                <label for="users-per-page" class="form-label"><?= esc(lang('MCMS.users.perPage')) ?></label>
                <select id="users-per-page" name="per_page" class="form-select">
                    <?php foreach ([10, 25, 50, 100] as $size): ?>
                        <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-12 d-flex align-items-center gap-2">
                <button type="submit" class="btn btn-outline-primary"><?= esc(lang('MCMS.common.search')) ?></button>
                <a class="btn btn-link" href="<?= site_url('admin/users') ?>"><?= esc(lang('MCMS.common.clear')) ?></a>
                <span id="users-loading" class="text-secondary small" role="status" aria-live="polite" hidden><?= esc(lang('MCMS.users.searching')) ?></span>
            </div>
        </form>
    </div>
</div>
<div id="users-results">
    <p class="text-secondary" aria-live="polite"><?= esc(sprintf(lang('MCMS.users.count'), $count)) ?></p>
    <form id="users-bulk" data-confirm="<?= esc(lang('MCMS.users.bulkConfirm'), 'attr') ?>" method="post" action="<?= site_url('admin/users/bulk') ?>">
        <?= csrf_field() ?>
        <?php if ($canSensitive): ?>
            <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                <label class="form-check mb-0">
                    <input type="checkbox" id="users-select-all" class="form-check-input" aria-label="<?= esc(lang('MCMS.users.selectAll'), 'attr') ?>">
                    <span class="form-check-label"><?= esc(lang('MCMS.users.selectAll')) ?></span>
                </label>
                <label class="form-label mb-0" for="users-bulk-action"><?= esc(lang('MCMS.users.bulkActions')) ?></label>
                <select id="users-bulk-action" class="form-select w-auto" name="action" required>
                    <option value=""><?= esc(lang('MCMS.users.chooseAction')) ?></option>
                    <option value="activate"><?= esc(lang('MCMS.users.activate')) ?></option>
                    <option value="deactivate"><?= esc(lang('MCMS.users.deactivate')) ?></option>
                    <option value="delete"><?= esc(lang('MCMS.users.delete')) ?></option>
                </select>
                <button class="btn btn-outline-primary" type="submit"><?= esc(lang('MCMS.users.apply')) ?></button>
                <span class="text-secondary small" id="users-selected" aria-live="polite"></span>
            </div>
        <?php endif ?>
        <div class="card">
            <table class="table table-vcenter card-table users-directory-table">
                <thead><tr>
                    <?php if ($canSensitive): ?>
                        <th class="users-directory-select"><span class="visually-hidden"><?= esc(lang('MCMS.users.selectAll')) ?></span></th>
                    <?php endif ?>
                    <th class="users-directory-person"><?= esc(lang('MCMS.common.user')) ?></th>
                    <th class="users-directory-role"><?= esc(lang('MCMS.users.access')) ?></th>
                    <th class="users-directory-status"><?= esc(lang('MCMS.common.status')) ?></th>
                    <th class="users-directory-dates"><?= esc(lang('MCMS.users.activityDates')) ?></th>
                    <th class="users-directory-actions"><?= esc(lang('MCMS.users.actions')) ?></th>
                </tr></thead>
                <tbody>
                    <?php if ($users === []): ?>
                        <tr class="users-directory-empty"><td colspan="<?= $canSensitive ? 6 : 5 ?>" class="text-secondary"><?= esc(lang('MCMS.users.empty')) ?></td></tr>
                    <?php endif ?>
                    <?php foreach ($users as $entry): ?>
                        <?php
                            $protected = in_array('superadmin', $entry->getGroups() ?? [], true)
                                && ! in_array('superadmin', $user->getGroups() ?? [], true);
                            $permissions = $entry->getPermissions() ?? [];
                        ?>
                        <tr>
                            <?php if ($canSensitive): ?>
                                <td class="users-directory-select">
                                    <input type="checkbox" class="form-check-input user-select" name="ids[]" value="<?= (int) $entry->id ?>"
                                        <?= (int) $entry->id === (int) $user->id || $protected ? 'disabled' : '' ?>
                                        aria-label="<?= esc(lang('MCMS.users.selectUser') . ' #' . $entry->id, 'attr') ?>">
                                </td>
                            <?php endif ?>
                            <td class="users-directory-person">
                                <div class="fw-semibold"><?= esc($entry->username ?? '—') ?></div>
                                <div class="text-secondary small users-directory-email"><?= esc($entry->email ?? '—') ?></div>
                                <div class="text-secondary small">ID: <?= (int) $entry->id ?></div>
                            </td>
                            <td class="users-directory-role">
                                <div class="text-secondary small"><?= esc(lang('MCMS.users.groups')) ?></div>
                                <div><?= esc(implode(', ', $entry->getGroups() ?? []) ?: '—') ?></div>
                                <?php if ($permissions !== []): ?>
                                    <details class="users-directory-permissions mt-1">
                                        <summary class="small"><?= esc(lang('MCMS.users.directPermissions')) ?> (<?= count($permissions) ?>)</summary>
                                        <div class="text-secondary small"><?= esc(implode(', ', $permissions)) ?></div>
                                    </details>
                                <?php endif ?>
                            </td>
                            <td class="users-directory-status">
                                <span class="badge <?= $entry->isBanned() ? 'bg-danger-lt' : 'bg-success-lt' ?>"><?= esc(lang('MCMS.users.' . ($entry->isBanned() ? 'blocked' : 'unblocked'))) ?></span>
                            </td>
                            <td class="users-directory-dates small">
                                <div class="users-directory-date">
                                    <span class="text-secondary"><?= esc(lang('MCMS.users.registered')) ?>:</span>
                                    <span><?= esc($entry->created_at ? (string) $entry->created_at : '—') ?></span>
                                </div>
                                <div class="users-directory-date mt-1">
                                    <span class="text-secondary"><?= esc(lang('MCMS.users.lastActive')) ?>:</span>
                                    <span><?= esc($entry->last_active ? (string) $entry->last_active : '—') ?></span>
                                </div>
                            </td>
                            <td class="users-directory-actions">
                                <div class="btn-list flex-nowrap">
                                    <?php if (! $protected): ?>
                                        <a class="btn btn-outline-primary btn-sm btn-icon admin-icon-action" href="<?= site_url('admin/users/' . $entry->id . '/edit') ?>" title="<?= esc(lang('MCMS.users.edit'), 'attr') ?>" aria-label="<?= esc(lang('MCMS.users.edit'), 'attr') ?>"><i class="ti ti-pencil"></i></a>
                                    <?php endif ?>
                                    <?php if ($canAccess && ! $protected): ?>
                                        <a class="btn btn-outline-primary btn-sm btn-icon admin-icon-action" href="<?= site_url('admin/users/' . $entry->id . '/access') ?>" title="<?= esc(lang('MCMS.users.access'), 'attr') ?>" aria-label="<?= esc(lang('MCMS.users.access'), 'attr') ?>"><i class="ti ti-user-shield"></i></a>
                                    <?php endif ?>
                                    <?php if ($canSensitive && ! $protected && (int) $entry->id !== (int) $user->id): ?>
                                        <a class="btn btn-outline-secondary btn-sm btn-icon admin-icon-action" href="<?= site_url('admin/users/' . $entry->id . '/edit') ?>#password" title="<?= esc(lang('MCMS.users.password'), 'attr') ?>" aria-label="<?= esc(lang('MCMS.users.password'), 'attr') ?>"><i class="ti ti-key"></i></a>
                                        <button type="submit" class="btn btn-outline-danger btn-sm btn-icon admin-icon-action" name="action" value="delete" form="user-action-<?= (int) $entry->id ?>" onclick="return confirm(<?= esc(json_encode(lang('MCMS.users.deleteConfirm')), 'attr') ?>)" title="<?= esc(lang('MCMS.users.delete'), 'attr') ?>" aria-label="<?= esc(lang('MCMS.users.delete'), 'attr') ?>"><i class="ti ti-trash"></i></button>
                                    <?php endif ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </form>
    <?php if ($canSensitive): ?>
        <?php foreach ($users as $entry): ?>
            <form id="user-action-<?= (int) $entry->id ?>" method="post" action="<?= site_url('admin/users/' . $entry->id . '/action') ?>"><?= csrf_field() ?></form>
        <?php endforeach ?>
    <?php endif ?>
    <?php if ($lastPage > 1): ?>
        <nav class="d-flex align-items-center justify-content-between gap-2 mt-3" aria-label="<?= esc(lang('MCMS.users.title'), 'attr') ?>">
            <span><?php if ($page > 1): ?><a class="btn btn-outline-secondary" href="<?= esc($pageUrl($page - 1), 'attr') ?>"><?= esc(lang('MCMS.common.previousPage')) ?></a><?php endif ?></span>
            <span class="text-secondary small"><?= (int) $page ?> / <?= (int) $lastPage ?></span>
            <span><?php if ($page < $lastPage): ?><a class="btn btn-outline-secondary" href="<?= esc($pageUrl($page + 1), 'attr') ?>"><?= esc(lang('MCMS.common.nextPage')) ?></a><?php endif ?></span>
        </nav>
    <?php endif ?>
</div>
<script src="<?= site_url('assets/admin/users.js') ?>" defer></script>
<?= $this->endSection() ?>
