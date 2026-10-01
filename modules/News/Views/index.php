<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md">
            <div class="page-pretitle"><?= esc(lang('MCMS.common.module')) ?></div>
            <h2 class="page-title"><?= esc(lang('News.Admin.title')) ?></h2>
            <div class="text-secondary mt-1"><?= esc(sprintf(lang('News.Admin.count'), $count)) ?></div>
        </div>
        <?php if ($canCreate): ?>
            <div class="col-12 col-md-auto ms-md-auto">
                <a class="btn btn-primary" href="<?= site_url('admin/news/create') ?>">
                    <i class="ti ti-plus me-1"></i><?= esc(lang('News.Admin.create')) ?>
                </a>
            </div>
        <?php endif ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2">
            <div class="col-md">
                <input class="form-control" type="search" name="q" value="<?= esc($query) ?>" placeholder="<?= esc(lang('News.Admin.searchPlaceholder'), 'attr') ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value=""><?= esc(lang('News.Admin.allStatuses')) ?></option>
                    <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>><?= esc(lang('News.Admin.statusDraft')) ?></option>
                    <option value="published" <?= $status === 'published' ? 'selected' : '' ?>><?= esc(lang('News.Admin.statusPublished')) ?></option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-outline-primary" type="submit"><i class="ti ti-search me-1"></i><?= esc(lang('MCMS.common.search')) ?></button>
            </div>
            <?php if ($query !== '' || $status !== null): ?>
                <div class="col-auto">
                    <a class="btn btn-link" href="<?= site_url('admin/news') ?>"><?= esc(lang('MCMS.common.clear')) ?></a>
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
                <th><?= esc(lang('News.Admin.slug')) ?></th>
                <th><?= esc(lang('MCMS.common.status')) ?></th>
                <th><?= esc(lang('News.Admin.publishedAt')) ?></th>
                <th class="w-1"></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($articles === []): ?>
                <tr><td colspan="5" class="text-secondary"><?= esc(lang('News.Admin.empty')) ?></td></tr>
            <?php endif ?>

            <?php foreach ($articles as $article): ?>
                <?php
                    $scheduled = $article['status'] === 'published' && ! $article['is_live'];
                    $statusKey = $article['status'] === 'draft'
                        ? 'News.Admin.statusDraft'
                        : ($scheduled ? 'News.Admin.statusScheduled' : 'News.Admin.statusPublished');
                    $statusClass = $article['status'] === 'draft'
                        ? 'bg-secondary-lt'
                        : ($scheduled ? 'bg-azure-lt' : 'bg-success-lt');
                ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <?php if (($article['featured_image_url'] ?? null) !== null): ?>
                                <img src="<?= esc(site_url(ltrim($article['featured_image_url'], '/')), 'attr') ?>" alt="" class="rounded" style="width:56px;height:40px;object-fit:cover">
                            <?php endif ?>
                            <div>
                                <div class="fw-bold"><?= esc($article['title']) ?></div>
                                <?php if (($article['excerpt'] ?? null) !== null): ?>
                                    <div class="text-secondary small text-truncate" style="max-width:32rem"><?= esc($article['excerpt']) ?></div>
                                <?php endif ?>
                            </div>
                        </div>
                    </td>
                    <td><code><?= esc($article['slug']) ?></code></td>
                    <td><span class="badge <?= $statusClass ?>"><?= esc(lang($statusKey)) ?></span></td>
                    <td><?= esc((string) ($article['published_at'] ?? '—')) ?></td>
                    <td>
                        <div class="btn-list flex-nowrap">
                            <?php if ($article['is_live']): ?>
                                <a class="btn btn-outline-secondary btn-sm" href="<?= esc(site_url(ltrim($article['url'], '/'))) ?>" target="_blank" rel="noopener">
                                    <i class="ti ti-external-link me-1"></i><?= esc(lang('News.Admin.viewPublic')) ?>
                                </a>
                            <?php endif ?>
                            <?php if ($canEdit): ?>
                                <a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/news/' . $article['id'] . '/edit') ?>">
                                    <i class="ti ti-pencil me-1"></i><?= esc(lang('News.Admin.edit')) ?>
                                </a>
                            <?php endif ?>
                            <?php if ($canDelete): ?>
                                <form method="post" action="<?= site_url('admin/news/' . $article['id'] . '/delete') ?>" onsubmit="return confirm(<?= esc(json_encode(lang('News.Admin.deleteConfirm')), 'attr') ?>);">
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
