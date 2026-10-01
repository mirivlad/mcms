<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle"><?= esc(lang('MCMS.users.title')) ?> / <?= esc(lang('MCMS.users.access')) ?></div>
            <h2 class="page-title"><?= esc($target->username ?? $target->email ?? (lang('MCMS.common.user') . ' #' . $target->id)) ?></h2>
        </div>
    </div>
</div>

<form method="post">
    <?= csrf_field() ?>

    <div class="row row-cards">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><?= esc(lang('MCMS.users.groups')) ?></h3></div>
                <div class="card-body">
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
                        <label class="form-check mb-2">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="groups[]"
                                value="<?= esc($id) ?>"
                                <?= in_array($id, $target->getGroups() ?? [], true) ? 'checked' : '' ?>
                            >
                            <span class="form-check-label">
                                <strong><?= esc($groupTitle) ?></strong>
                                <?php if ($groupDescription !== ''): ?>
                                    <span class="d-block text-secondary"><?= esc($groupDescription) ?></span>
                                <?php endif ?>
                            </span>
                        </label>
                    <?php endforeach ?>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><?= esc(lang('MCMS.users.directPermissions')) ?></h3></div>
                <div class="card-body">
                    <p class="text-secondary"><?= esc(lang('MCMS.users.accessIntro')) ?></p>
                    <?php foreach ($permissions as $permission => $description): ?>
                        <?php
                            $permissionKey = 'MCMS.permissions.' . str_replace('.', '_', $permission);
                            $translated = lang($permissionKey);
                            $permissionDescription = $translated === $permissionKey ? $description : $translated;
                        ?>
                        <label class="form-check mb-2">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="permissions[]"
                                value="<?= esc($permission) ?>"
                                <?= in_array($permission, $target->getPermissions() ?? [], true) ? 'checked' : '' ?>
                            >
                            <span class="form-check-label">
                                <code><?= esc($permission) ?></code>
                                <span class="d-block text-secondary"><?= esc($permissionDescription) ?></span>
                            </span>
                        </label>
                    <?php endforeach ?>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <button class="btn btn-primary" type="submit"><?= esc(lang('MCMS.users.saveAccess')) ?></button>
        <a class="btn btn-link" href="<?= site_url('admin/users') ?>"><?= esc(lang('MCMS.users.back')) ?></a>
    </div>
</form>
<?= $this->endSection() ?>
