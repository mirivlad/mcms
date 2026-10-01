<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle"><?= esc(lang('MCMS.common.core')) ?></div>
            <h2 class="page-title"><?= esc(lang('MCMS.access.title')) ?></h2>
            <div class="text-secondary mt-1"><?= esc(lang('MCMS.access.intro')) ?></div>
        </div>
    </div>
</div>

<div class="row row-cards">
    <?php foreach ($groups as $id => $group): ?>
        <?php
            $titleKey = 'MCMS.groups.' . $id . 'Title';
            $descriptionKey = 'MCMS.groups.' . $id . 'Description';
            $translatedTitle = lang($titleKey);
            $translatedDescription = lang($descriptionKey);
            $groupTitle = $translatedTitle === $titleKey ? ($group['title'] ?? $id) : $translatedTitle;
            $groupDescription = $translatedDescription === $descriptionKey
                ? ($group['description'] ?? '')
                : $translatedDescription;
        ?>
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                        <h3 class="card-title"><?= esc($groupTitle) ?></h3>
                        <div class="text-secondary"><code><?= esc($id) ?></code></div>
                    </div>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= site_url('admin/access/group') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= esc($id) ?>">

                        <div class="mb-3">
                            <label class="form-label"><?= esc(lang('MCMS.access.groupTitle')) ?></label>
                            <input class="form-control" name="title" required value="<?= esc($group['title'] ?? $id) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?= esc(lang('MCMS.access.groupDescription')) ?></label>
                            <input class="form-control" name="description" value="<?= esc($group['description'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?= esc(lang('MCMS.common.permissions')) ?></label>
                            <?php if ($id === 'superadmin'): ?>
                                <div class="alert alert-info mb-0">
                                    <?= esc(lang('MCMS.access.superadminInfo')) ?>
                                </div>
                            <?php else: ?>
                                <div class="border rounded p-3" style="max-height: 20rem; overflow:auto">
                                    <?php foreach ($permissions as $permission => $description): ?>
                                        <?php
                                            $permissionKey = 'MCMS.permissions.' . str_replace('.', '_', $permission);
                                            $translated = lang($permissionKey);
                                            $permissionDescription = $translated === $permissionKey ? $description : $translated;
                                            if (is_string($permissionDescription) && str_starts_with($permissionDescription, '@')) {
                                                $permissionDescription = lang(substr($permissionDescription, 1));
                                            }
                                        ?>
                                        <label class="form-check mb-2">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                name="permissions[]"
                                                value="<?= esc($permission) ?>"
                                                <?= in_array($permission, $matrix[$id] ?? [], true) ? 'checked' : '' ?>
                                            >
                                            <span class="form-check-label">
                                                <code><?= esc($permission) ?></code>
                                                <span class="d-block text-secondary"><?= esc($permissionDescription) ?></span>
                                            </span>
                                        </label>
                                    <?php endforeach ?>
                                </div>
                            <?php endif ?>
                        </div>

                        <button class="btn btn-primary" type="submit"><?= esc(lang('MCMS.access.saveGroup')) ?></button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach ?>

    <div class="col-xl-6">
        <div class="card h-100 border-dashed">
            <div class="card-header">
                <h3 class="card-title"><?= esc(lang('MCMS.access.createGroup')) ?></h3>
            </div>
            <div class="card-body">
                <form method="post" action="<?= site_url('admin/access/group') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="new_group_id"><?= esc(lang('MCMS.access.groupId')) ?></label>
                        <input
                            class="form-control"
                            id="new_group_id"
                            name="id"
                            pattern="[a-z][a-z0-9_-]*"
                            placeholder="editor"
                            required
                        >
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="new_group_title"><?= esc(lang('MCMS.access.groupTitle')) ?></label>
                        <input class="form-control" id="new_group_title" name="title" placeholder="Editor" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="new_group_description"><?= esc(lang('MCMS.access.groupDescription')) ?></label>
                        <input class="form-control" id="new_group_description" name="description">
                    </div>
                    <p class="text-secondary"><?= esc(lang('MCMS.access.createHint')) ?></p>
                    <button class="btn btn-outline-primary" type="submit"><?= esc(lang('MCMS.access.createGroup')) ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
