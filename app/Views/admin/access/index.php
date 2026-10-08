<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
    $groupData = [];
    $coreGroups = config('AuthGroups')->groups;
    foreach ($groups as $id => $group) {
        $titleKey = 'MCMS.groups.' . $id . 'Title';
        $descriptionKey = 'MCMS.groups.' . $id . 'Description';
        $translatedTitle = lang($titleKey);
        $translatedDescription = lang($descriptionKey);
        $title = $group['title'] ?? $id;
        $description = $group['description'] ?? '';
        $groupData[$id] = [
            'title' => $title,
            'description' => $description,
            'label' => $title === ($coreGroups[$id]['title'] ?? null) && $translatedTitle !== $titleKey ? $translatedTitle : $title,
            'summary' => $description === ($coreGroups[$id]['description'] ?? null) && $translatedDescription !== $descriptionKey ? $translatedDescription : $description,
            'permissions' => $matrix[$id] ?? [],
            'members' => (int) ($memberCounts[$id] ?? 0),
            'protected' => in_array($id, $protectedGroups, true),
        ];
    }
    $retryMode = session()->getFlashdata('accessGroupMode');
    $retry = in_array($retryMode, ['create', 'edit'], true) ? [
        'mode' => $retryMode,
        'id' => (string) old('id', '', false),
        'title' => (string) old('title', '', false),
        'description' => (string) old('description', '', false),
        'permissions' => (array) old('permissions', [], false),
    ] : null;
?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle"><?= esc(lang('MCMS.common.core')) ?></div>
            <h2 class="page-title"><?= esc(lang('MCMS.access.title')) ?></h2>
            <div class="text-secondary mt-1"><?= esc(lang('MCMS.access.intro')) ?></div>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" type="button" id="access-create-group"><?= esc(lang('MCMS.access.createGroup')) ?></button>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-vcenter card-table access-groups-table">
            <caption class="visually-hidden"><?= esc(lang('MCMS.common.groups')) ?></caption>
            <thead>
                <tr>
                    <th scope="col"><?= esc(lang('MCMS.common.groups')) ?></th>
                    <th scope="col"><?= esc(lang('MCMS.common.description')) ?></th>
                    <th scope="col"><?= esc(lang('MCMS.common.permissions')) ?></th>
                    <th scope="col"><?= esc(lang('MCMS.users.title')) ?></th>
                    <th scope="col"><?= esc(lang('MCMS.access.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groupData as $id => $group): ?>
                    <tr>
                        <td data-label="<?= esc(lang('MCMS.common.groups'), 'attr') ?>">
                            <strong><?= esc($group['label']) ?></strong>
                            <div><code><?= esc($id) ?></code></div>
                            <?php if ($group['protected']): ?>
                                <span class="badge bg-secondary-lt mt-1"><?= esc(lang('MCMS.access.systemGroup')) ?></span>
                            <?php endif ?>
                        </td>
                        <td data-label="<?= esc(lang('MCMS.common.description'), 'attr') ?>" class="text-secondary"><?= esc($group['summary']) ?></td>
                        <td data-label="<?= esc(lang('MCMS.common.permissions'), 'attr') ?>"><?= $id === 'superadmin' ? esc(lang('MCMS.access.allPermissions')) : count($group['permissions']) ?></td>
                        <td data-label="<?= esc(lang('MCMS.users.title'), 'attr') ?>"><?= $group['members'] ?></td>
                        <td data-label="<?= esc(lang('MCMS.access.actions'), 'attr') ?>">
                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn btn-outline-primary btn-sm btn-icon admin-icon-action" type="button" data-edit-group="<?= esc($id, 'attr') ?>" aria-label="<?= esc(lang('MCMS.access.editGroup') . ': ' . $group['label'], 'attr') ?>" title="<?= esc(lang('MCMS.access.editGroup'), 'attr') ?>" data-bs-toggle="tooltip" data-bs-container="body"><i class="ti ti-pencil" aria-hidden="true"></i></button>
                                <button class="btn btn-outline-danger btn-sm btn-icon admin-icon-action" type="button" data-delete-group="<?= esc($id, 'attr') ?>" aria-label="<?= esc(lang('MCMS.common.delete') . ': ' . $group['label'], 'attr') ?>" title="<?= esc(lang('MCMS.common.delete'), 'attr') ?>" data-bs-toggle="tooltip" data-bs-container="body" <?= $group['protected'] ? 'disabled' : '' ?>><i class="ti ti-trash" aria-hidden="true"></i></button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer text-secondary"><?= esc(lang('MCMS.access.protectedHint')) ?></div>
</div>

<dialog class="access-dialog" id="access-group-dialog" aria-labelledby="access-group-heading">
    <div class="card-header d-flex justify-content-between gap-3">
        <h3 class="card-title" id="access-group-heading"></h3>
        <button class="btn-close" type="button" data-close-dialog aria-label="<?= esc(lang('MCMS.common.cancel'), 'attr') ?>"></button>
    </div>
    <form method="post" action="<?= site_url('admin/access/group') ?>" id="access-group-form">
        <?= csrf_field() ?>
        <input type="hidden" name="mode" id="access-group-mode">
        <div class="access-dialog-body">
            <div class="alert alert-danger" role="alert" id="access-group-error" hidden><?= esc(session()->getFlashdata('error') ?? '') ?></div>
            <div class="mb-3">
                <label class="form-label" for="access-group-id"><?= esc(lang('MCMS.access.groupId')) ?></label>
                <input class="form-control" id="access-group-id" name="id" pattern="[a-z][a-z0-9_-]*" placeholder="editor" required aria-describedby="access-group-id-hint">
                <div class="form-hint" id="access-group-id-hint"></div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="access-group-title"><?= esc(lang('MCMS.access.groupTitle')) ?></label>
                <input class="form-control" id="access-group-title" name="title" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="access-group-description"><?= esc(lang('MCMS.access.groupDescription')) ?></label>
                <textarea class="form-control" id="access-group-description" name="description" rows="2"></textarea>
            </div>
            <div class="alert alert-info" id="access-superadmin-info" hidden><?= esc(lang('MCMS.access.superadminInfo')) ?></div>
            <fieldset id="access-group-permissions">
                <legend class="form-label"><?= esc(lang('MCMS.common.permissions')) ?></legend>
                <?php foreach ($permissions as $permission => $description): ?>
                    <?php
                        $permissionKey = 'MCMS.permissions.' . str_replace('.', '_', $permission);
                        $translated = lang($permissionKey);
                        $permissionDescription = $translated === $permissionKey ? $description : $translated;
                        if (is_string($permissionDescription) && str_starts_with($permissionDescription, '@')) {
                            $permissionDescription = lang(substr($permissionDescription, 1));
                        }
                    ?>
                    <label class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= esc($permission, 'attr') ?>">
                        <span class="form-check-label">
                            <code><?= esc($permission) ?></code>
                            <span class="d-block text-secondary"><?= esc($permissionDescription) ?></span>
                        </span>
                    </label>
                <?php endforeach ?>
            </fieldset>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <button class="btn" type="button" data-close-dialog><?= esc(lang('MCMS.common.cancel')) ?></button>
            <button class="btn btn-primary" type="submit" id="access-group-submit"><?= esc(lang('MCMS.access.saveGroup')) ?></button>
        </div>
    </form>
</dialog>

<dialog class="access-dialog access-delete-dialog" id="access-delete-dialog" aria-labelledby="access-delete-heading" aria-describedby="access-delete-warning">
    <div class="card-header d-flex justify-content-between gap-3">
        <h3 class="card-title" id="access-delete-heading"><?= esc(lang('MCMS.access.deleteGroup')) ?></h3>
        <button class="btn-close" type="button" data-close-dialog aria-label="<?= esc(lang('MCMS.common.cancel'), 'attr') ?>"></button>
    </div>
    <form method="post" action="<?= site_url('admin/access/group/delete') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="access-delete-id">
        <input type="hidden" name="confirm" value="1">
        <div class="access-dialog-body">
            <p class="fw-bold" id="access-delete-name"></p>
            <p class="text-secondary" id="access-delete-warning"><?= esc(lang('MCMS.access.deleteWarning')) ?></p>
            <p class="mb-0"><?= esc(lang('MCMS.users.title')) ?>: <strong id="access-delete-members"></strong></p>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <button class="btn" type="button" data-close-dialog id="access-delete-cancel"><?= esc(lang('MCMS.common.cancel')) ?></button>
            <button class="btn btn-danger" type="submit"><?= esc(lang('MCMS.common.delete')) ?></button>
        </div>
    </form>
</dialog>
<script>
(() => {
    const groups = <?= json_encode((object) $groupData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const retry = <?= json_encode($retry, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const messages = <?= json_encode([
        'create' => lang('MCMS.access.createGroup'), 'edit' => lang('MCMS.access.editGroup'),
        'idHint' => lang('MCMS.access.createHint'), 'fixedId' => lang('MCMS.access.fixedId'),
        'save' => lang('MCMS.access.saveGroup'),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const dialog = document.getElementById('access-group-dialog');
    const deleteDialog = document.getElementById('access-delete-dialog');
    const form = document.getElementById('access-group-form');
    const idInput = document.getElementById('access-group-id');
    const titleInput = document.getElementById('access-group-title');
    const permissionFields = document.getElementById('access-group-permissions');
    const error = document.getElementById('access-group-error');
    const openEditor = (mode, id = '', values = null) => {
        form.reset();
        const group = values || (Object.hasOwn(groups, id) ? groups[id] : {});
        document.getElementById('access-group-mode').value = mode;
        document.getElementById('access-group-heading').textContent = messages[mode];
        document.getElementById('access-group-submit').textContent = mode === 'create' ? messages.create : messages.save;
        idInput.value = id;
        idInput.readOnly = mode === 'edit';
        document.getElementById('access-group-id-hint').textContent = mode === 'create' ? messages.idHint : messages.fixedId;
        titleInput.value = group.title || '';
        document.getElementById('access-group-description').value = group.description || '';
        const isSuperadmin = mode === 'edit' && id === 'superadmin';
        permissionFields.hidden = permissionFields.disabled = isSuperadmin;
        document.getElementById('access-superadmin-info').hidden = !isSuperadmin;
        permissionFields.querySelectorAll('input').forEach(input => input.checked = (group.permissions || []).includes(input.value));
        error.hidden = !values;
        dialog.showModal();
        (mode === 'create' ? idInput : titleInput).focus();
    };
    document.getElementById('access-create-group').addEventListener('click', () => openEditor('create'));
    document.querySelectorAll('[data-edit-group]').forEach(button => button.addEventListener('click', () => openEditor('edit', button.dataset.editGroup)));
    document.querySelectorAll('[data-delete-group]').forEach(button => button.addEventListener('click', () => {
        const id = button.dataset.deleteGroup;
        if (!Object.hasOwn(groups, id) || groups[id].protected) return;
        document.getElementById('access-delete-id').value = id;
        document.getElementById('access-delete-name').textContent = groups[id].label + ' (' + id + ')';
        document.getElementById('access-delete-members').textContent = groups[id].members;
        deleteDialog.showModal();
        document.getElementById('access-delete-cancel').focus();
    }));
    document.querySelectorAll('[data-close-dialog]').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
    if (retry) openEditor(retry.mode, retry.id, retry);
})();
</script>
<?= $this->endSection() ?>
