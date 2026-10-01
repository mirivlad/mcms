<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md">
            <div class="page-pretitle"><?= esc(lang('MCMS.common.module')) ?></div>
            <h2 class="page-title"><?= esc(lang('Pages.Admin.title')) ?></h2>
            <div class="text-secondary mt-1"><?= esc(sprintf(lang('Pages.Admin.count'), $count)) ?></div>
        </div>
        <div class="col-12 col-md-auto ms-md-auto d-flex flex-wrap gap-2">
            <?php if ($canSettings): ?>
                <a class="btn btn-outline-secondary" href="<?= site_url('admin/pages/settings') ?>">
                    <i class="ti ti-settings me-1"></i><?= esc(lang('Pages.Admin.settings')) ?>
                </a>
            <?php endif ?>
            <?php if ($canCreate): ?>
                <a class="btn btn-primary" href="<?= site_url('admin/pages/create') ?>">
                    <i class="ti ti-plus me-1"></i><?= esc(lang('Pages.Admin.create')) ?>
                </a>
            <?php endif ?>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2">
            <div class="col-md">
                <input
                    class="form-control"
                    type="search"
                    name="q"
                    value="<?= esc($query) ?>"
                    placeholder="<?= esc(lang('Pages.Admin.searchPlaceholder'), 'attr') ?>"
                >
            </div>
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value=""><?= esc(lang('Pages.Admin.allStatuses')) ?></option>
                    <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>
                        <?= esc(lang('Pages.Admin.statusDraft')) ?>
                    </option>
                    <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>
                        <?= esc(lang('Pages.Admin.statusPublished')) ?>
                    </option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-outline-primary" type="submit"><i class="ti ti-search me-1"></i><?= esc(lang('MCMS.common.search')) ?></button>
            </div>
            <?php if ($query !== '' || $status !== null): ?>
                <div class="col-auto">
                    <a class="btn btn-link" href="<?= site_url('admin/pages') ?>"><?= esc(lang('MCMS.common.clear')) ?></a>
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
                <th><?= esc(lang('MCMS.common.title')) ?></th>
                <th><?= esc(lang('Pages.Admin.slug')) ?></th>
                <th><?= esc(lang('MCMS.common.status')) ?></th>
                <th><?= esc(lang('Pages.Admin.updatedAt')) ?></th>
                <th class="w-1"></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($pages === []): ?>
                <tr>
                    <td colspan="5" class="text-secondary"><?= esc(lang('Pages.Admin.empty')) ?></td>
                </tr>
            <?php endif ?>

            <?php foreach ($pages as $page): ?>
                <tr>
                    <td>
                        <div class="fw-bold"><?= esc($page['title']) ?></div>
                        <?php if (($page['seo_title'] ?? null) !== null): ?>
                            <div class="text-secondary small"><?= esc($page['seo_title']) ?></div>
                        <?php endif ?>
                    </td>
                    <td><code><?= esc($page['slug']) ?></code></td>
                    <td>
                        <span class="badge <?= $page['status'] === 'published' ? 'bg-success-lt' : 'bg-secondary-lt' ?>">
                            <?= esc(lang($page['status'] === 'published' ? 'Pages.Admin.statusPublished' : 'Pages.Admin.statusDraft')) ?>
                        </span>
                    </td>
                    <td><?= esc((string) ($page['updated_at'] ?? $page['created_at'])) ?></td>
                    <td>
                        <div class="btn-list flex-nowrap">
                            <?php if ($page['status'] === 'published'): ?>
                                <a class="btn btn-outline-secondary btn-sm" href="<?= esc(site_url(ltrim($page['url'], '/'))) ?>" target="_blank" rel="noopener">
                                    <i class="ti ti-external-link me-1"></i><?= esc(lang('Pages.Admin.viewPublic')) ?>
                                </a>
                            <?php endif ?>
                            <?php if ($canEdit): ?>
                                <a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/pages/' . $page['id'] . '/edit') ?>">
                                    <i class="ti ti-pencil me-1"></i><?= esc(lang('Pages.Admin.edit')) ?>
                                </a>
                            <?php endif ?>
                            <?php if ($canDelete): ?>
                                <form
                                    method="post"
                                    action="<?= site_url('admin/pages/' . $page['id'] . '/delete') ?>"
                                    onsubmit="return confirm(<?= esc(json_encode(lang('Pages.Admin.deleteConfirm')), 'attr') ?>);"
                                >
                                    <?= csrf_field() ?>
                                    <button class="btn btn-outline-danger btn-sm" type="submit"><i class="ti ti-trash me-1"></i><?= esc(lang('MCMS.common.delete')) ?></button>
                                </form>
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