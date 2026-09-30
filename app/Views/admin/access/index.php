<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">Core</div>
            <h2 class="page-title">Access control</h2>
            <div class="text-secondary mt-1">
                Groups inherit permissions from Shield's authorization matrix. Module permissions appear here automatically.
            </div>
        </div>
    </div>
</div>

<div class="row row-cards">
    <?php foreach ($groups as $id => $group): ?>
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                        <h3 class="card-title"><?= esc($group['title'] ?? $id) ?></h3>
                        <div class="text-secondary"><code><?= esc($id) ?></code></div>
                    </div>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= site_url('admin/access/group') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= esc($id) ?>">

                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input class="form-control" name="title" required value="<?= esc($group['title'] ?? $id) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <input class="form-control" name="description" value="<?= esc($group['description'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Permissions</label>
                            <div class="border rounded p-3" style="max-height: 20rem; overflow:auto">
                                <?php foreach ($permissions as $permission => $description): ?>
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
                                            <span class="d-block text-secondary"><?= esc($description) ?></span>
                                        </span>
                                    </label>
                                <?php endforeach ?>
                            </div>
                        </div>

                        <button class="btn btn-primary" type="submit">Save group</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach ?>

    <div class="col-xl-6">
        <div class="card h-100 border-dashed">
            <div class="card-header">
                <h3 class="card-title">Create group</h3>
            </div>
            <div class="card-body">
                <form method="post" action="<?= site_url('admin/access/group') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="new_group_id">Group ID</label>
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
                        <label class="form-label" for="new_group_title">Title</label>
                        <input class="form-control" id="new_group_title" name="title" placeholder="Editor" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="new_group_description">Description</label>
                        <input class="form-control" id="new_group_description" name="description">
                    </div>
                    <p class="text-secondary">
                        Create the group first, then assign its permissions from the group card.
                    </p>
                    <button class="btn btn-outline-primary" type="submit">Create group</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
