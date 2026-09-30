<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">Users / Access</div>
            <h2 class="page-title"><?= esc($target->username ?? $target->email ?? ('User #' . $target->id)) ?></h2>
        </div>
    </div>
</div>

<form method="post">
    <?= csrf_field() ?>

    <div class="row row-cards">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Groups</h3></div>
                <div class="card-body">
                    <?php foreach ($groups as $id => $group): ?>
                        <label class="form-check mb-2">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="groups[]"
                                value="<?= esc($id) ?>"
                                <?= in_array($id, $target->getGroups() ?? [], true) ? 'checked' : '' ?>
                            >
                            <span class="form-check-label">
                                <strong><?= esc($group['title'] ?? $id) ?></strong>
                                <?php if (($group['description'] ?? '') !== ''): ?>
                                    <span class="d-block text-secondary"><?= esc($group['description']) ?></span>
                                <?php endif ?>
                            </span>
                        </label>
                    <?php endforeach ?>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Direct permissions</h3></div>
                <div class="card-body">
                    <p class="text-secondary">
                        These permissions are assigned directly to the user in addition to permissions inherited from groups.
                    </p>
                    <?php foreach ($permissions as $permission => $description): ?>
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
                                <span class="d-block text-secondary"><?= esc($description) ?></span>
                            </span>
                        </label>
                    <?php endforeach ?>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <button class="btn btn-primary" type="submit">Save access</button>
        <a class="btn btn-link" href="<?= site_url('admin/users') ?>">Back to users</a>
    </div>
</form>
<?= $this->endSection() ?>
