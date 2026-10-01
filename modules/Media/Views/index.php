<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle"><?= esc(lang('MCMS.common.module')) ?></div>
            <h2 class="page-title"><?= esc(lang('MCMS.media.title')) ?></h2>
            <div class="text-secondary mt-1">
                <?= esc(sprintf(lang($count === 1 ? 'MCMS.media.item' : 'MCMS.media.items'), $count)) ?>
                <?= $query !== '' ? esc(sprintf(lang('MCMS.media.matching'), $query)) : esc(lang('MCMS.media.inLibrary')) ?>
            </div>
        </div>
        <?php if ($canSettings): ?>
            <div class="col-auto ms-auto">
                <a class="btn btn-outline-secondary" href="<?= site_url('admin/media/settings') ?>">
                    <?= esc(lang('MCMS.media.settings')) ?>
                </a>
            </div>
        <?php endif ?>
    </div>
</div>

<div class="row row-cards">
    <?php if ($canUpload): ?>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= esc(lang('MCMS.media.upload')) ?></h3>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= site_url('admin/media/upload') ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label class="form-label" for="media_file"><?= esc(lang('MCMS.media.file')) ?></label>
                            <input
                                class="form-control"
                                id="media_file"
                                type="file"
                                name="file"
                                accept="image/jpeg,image/png,image/webp,image/gif,application/pdf"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="media_title"><?= esc(lang('MCMS.common.title')) ?></label>
                            <input class="form-control" id="media_title" name="title" maxlength="255">
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="media_alt"><?= esc(lang('MCMS.media.altText')) ?></label>
                            <input class="form-control" id="media_alt" name="alt_text" maxlength="255">
                            <div class="form-hint"><?= esc(lang('MCMS.media.altHint')) ?></div>
                        </div>

                        <button class="btn btn-primary w-100" type="submit"><?= esc(lang('MCMS.media.uploadFile')) ?></button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif ?>

    <div class="<?= $canUpload ? 'col-lg-8' : 'col-12' ?>">
        <div class="card">
            <div class="card-body">
                <form method="get" class="row g-2">
                    <div class="col">
                        <input
                            class="form-control"
                            name="q"
                            value="<?= esc($query) ?>"
                            placeholder="<?= esc(lang('MCMS.media.searchPlaceholder'), 'attr') ?>"
                        >
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-outline-primary" type="submit"><?= esc(lang('MCMS.common.search')) ?></button>
                    </div>
                    <?php if ($query !== ''): ?>
                        <div class="col-auto">
                            <a class="btn btn-link" href="<?= site_url('admin/media') ?>"><?= esc(lang('MCMS.common.clear')) ?></a>
                        </div>
                    <?php endif ?>
                </form>
            </div>
        </div>

        <div class="row row-cards mt-1">
            <?php if ($items === []): ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-secondary">
                            <?= esc(lang('MCMS.media.empty')) ?>
                        </div>
                    </div>
                </div>
            <?php endif ?>

            <?php foreach ($items as $item): ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-auto">
                                    <?php if ($item['is_image']): ?>
                                        <a href="<?= esc($item['url']) ?>" target="_blank" rel="noopener">
                                            <img
                                                src="<?= esc($item['url']) ?>"
                                                alt=""
                                                width="96"
                                                height="96"
                                                class="rounded border"
                                                style="object-fit:cover"
                                            >
                                        </a>
                                    <?php else: ?>
                                        <div
                                            class="rounded border d-flex align-items-center justify-content-center bg-body-tertiary"
                                            style="width:96px;height:96px"
                                        >
                                            <span class="fw-bold text-secondary">PDF</span>
                                        </div>
                                    <?php endif ?>
                                </div>

                                <div class="col min-w-0">
                                    <div class="fw-bold text-truncate"><?= esc($item['original_name']) ?></div>
                                    <div class="text-secondary small mb-2">
                                        <?= esc($item['mime_type']) ?>
                                        · <?= esc(number_format($item['size_bytes'] / 1024, 1)) ?> KB
                                        <?php if ($item['width'] !== null && $item['height'] !== null): ?>
                                            · <?= esc((string) $item['width']) ?>×<?= esc((string) $item['height']) ?>
                                        <?php endif ?>
                                    </div>

                                    <div class="input-group input-group-sm mb-3">
                                        <span class="input-group-text">URL</span>
                                        <input class="form-control" readonly value="<?= esc($item['url']) ?>">
                                        <a class="btn btn-outline-secondary" href="<?= esc($item['url']) ?>" target="_blank" rel="noopener">
                                            <?= esc(lang('MCMS.common.open')) ?>
                                        </a>
                                    </div>

                                    <?php if ($canEdit): ?>
                                        <form method="post" action="<?= site_url('admin/media/' . $item['id'] . '/metadata') ?>" class="row g-2">
                                            <?= csrf_field() ?>
                                            <div class="col-md-6">
                                                <input
                                                    class="form-control form-control-sm"
                                                    name="title"
                                                    maxlength="255"
                                                    placeholder="<?= esc(lang('MCMS.common.title'), 'attr') ?>"
                                                    value="<?= esc((string) ($item['title'] ?? '')) ?>"
                                                >
                                            </div>
                                            <div class="col-md-6">
                                                <input
                                                    class="form-control form-control-sm"
                                                    name="alt_text"
                                                    maxlength="255"
                                                    placeholder="<?= esc(lang('MCMS.media.altText'), 'attr') ?>"
                                                    value="<?= esc((string) ($item['alt_text'] ?? '')) ?>"
                                                >
                                            </div>
                                            <div class="col-auto">
                                                <button class="btn btn-outline-primary btn-sm" type="submit">
                                                    <?= esc(lang('MCMS.media.saveMetadata')) ?>
                                                </button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <?php if (($item['title'] ?? null) !== null): ?>
                                            <div><strong><?= esc(lang('MCMS.common.title')) ?>:</strong> <?= esc($item['title']) ?></div>
                                        <?php endif ?>
                                        <?php if (($item['alt_text'] ?? null) !== null): ?>
                                            <div><strong>Alt:</strong> <?= esc($item['alt_text']) ?></div>
                                        <?php endif ?>
                                    <?php endif ?>
                                </div>

                                <?php if ($canDelete): ?>
                                    <div class="col-auto">
                                        <form
                                            method="post"
                                            action="<?= site_url('admin/media/' . $item['id'] . '/delete') ?>"
                                            onsubmit="return confirm(<?= esc(json_encode(lang('MCMS.media.deleteConfirm')), 'attr') ?>);"
                                        >
                                            <?= csrf_field() ?>
                                            <button class="btn btn-outline-danger btn-sm" type="submit"><?= esc(lang('MCMS.common.delete')) ?></button>
                                        </form>
                                    </div>
                                <?php endif ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
