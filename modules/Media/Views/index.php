<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$pageUrl = static function (int $targetPage) use ($query): string {
    $params = [];
    if ($query !== '') {
        $params['q'] = $query;
    }
    if ($targetPage > 1) {
        $params['page'] = $targetPage;
    }

    $url = site_url('admin/media');

    return $params === [] ? $url : $url . '?' . http_build_query($params);
};
?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md">
            <div class="page-pretitle"><?= esc(lang('MCMS.common.module')) ?></div>
            <h2 class="page-title"><?= esc(lang('MCMS.media.title')) ?></h2>
            <div class="text-secondary mt-1">
                <?= esc(sprintf(lang($count === 1 ? 'MCMS.media.item' : 'MCMS.media.items'), $count)) ?>
                <?= $query !== '' ? esc(sprintf(lang('MCMS.media.matching'), $query)) : esc(lang('MCMS.media.inLibrary')) ?>
            </div>
        </div>
        <div class="col-12 col-md-auto ms-md-auto d-flex flex-wrap gap-2">
            <?php if ($canUpload): ?>
                <button class="btn btn-primary" type="button" id="toggle-media-upload">
                    <i class="ti ti-upload me-1"></i>
                    <span><?= esc(lang('MCMS.media.uploadPanel')) ?></span>
                </button>
            <?php endif ?>
            <?php if ($canSettings): ?>
                <a class="btn btn-outline-secondary" href="<?= site_url('admin/media/settings') ?>">
                    <i class="ti ti-settings me-1"></i>
                    <?= esc(lang('MCMS.media.settings')) ?>
                </a>
            <?php endif ?>
        </div>
    </div>
</div>

<?php if ($canUpload): ?>
    <div class="card mb-3" id="media-upload-panel" hidden>
        <div class="card-header">
            <h3 class="card-title">
                <i class="ti ti-upload me-2"></i><?= esc(lang('MCMS.media.upload')) ?>
            </h3>
        </div>
        <div class="card-body">
            <form method="post" action="<?= site_url('admin/media/upload') ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <div class="row g-3 align-items-end">
                    <div class="col-lg-4">
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

                    <div class="col-lg-3">
                        <label class="form-label" for="media_title"><?= esc(lang('MCMS.common.title')) ?></label>
                        <input class="form-control" id="media_title" name="title" maxlength="255">
                    </div>

                    <div class="col-lg-3">
                        <label class="form-label" for="media_alt"><?= esc(lang('MCMS.media.altText')) ?></label>
                        <input class="form-control" id="media_alt" name="alt_text" maxlength="255">
                    </div>

                    <div class="col-lg-2">
                        <button class="btn btn-primary w-100" type="submit">
                            <i class="ti ti-cloud-upload me-1"></i>
                            <?= esc(lang('MCMS.media.uploadFile')) ?>
                        </button>
                    </div>
                </div>

                <div class="form-hint mt-2"><?= esc(lang('MCMS.media.altHint')) ?></div>
            </form>
        </div>
    </div>
<?php endif ?>

<div class="card mb-3">
    <div class="card-body">
        <form method="get">
            <div class="input-group">
                <span class="input-group-text"><i class="ti ti-search"></i></span>
                <input
                    class="form-control"
                    type="search"
                    name="q"
                    value="<?= esc($query) ?>"
                    placeholder="<?= esc(lang('MCMS.media.searchPlaceholder'), 'attr') ?>"
                >
                <button class="btn btn-outline-primary" type="submit">
                    <?= esc(lang('MCMS.common.search')) ?>
                </button>
                <?php if ($query !== ''): ?>
                    <a class="btn btn-outline-secondary" href="<?= site_url('admin/media') ?>" aria-label="<?= esc(lang('MCMS.common.clear'), 'attr') ?>">
                        <i class="ti ti-x"></i>
                    </a>
                <?php endif ?>
            </div>
        </form>
    </div>
</div>

<?php if ($items === []): ?>
    <div class="empty">
        <div class="empty-img">
            <i class="ti ti-photo-off" style="font-size:4rem"></i>
        </div>
        <p class="empty-title"><?= esc(lang('MCMS.media.empty')) ?></p>
    </div>
<?php else: ?>
    <div class="row row-cards">
        <?php foreach ($items as $item): ?>
            <?php
                $extension = strtoupper((string) ($item['extension'] ?? ''));
                $sizeKb = number_format($item['size_bytes'] / 1024, 1);
            ?>
            <div class="col-sm-6 col-xl-4">
                <div class="card h-100">
                    <div
                        class="card-img-top position-relative d-flex align-items-center justify-content-center bg-body-tertiary overflow-hidden"
                        style="height:220px"
                    >
                        <?php if ($item['is_image']): ?>
                            <img
                                src="<?= esc($item['thumbnail_url']) ?>"
                                alt="<?= esc((string) ($item['alt_text'] ?? '')) ?>"
                                class="w-100 h-100"
                                style="object-fit:contain"
                                loading="lazy"
                            >
                        <?php else: ?>
                            <i class="ti ti-file-type-pdf text-secondary" style="font-size:5rem"></i>
                        <?php endif ?>

                        <?php if ($extension !== ''): ?>
                            <span class="badge bg-dark-lt position-absolute top-0 end-0 m-2"><?= esc($extension) ?></span>
                        <?php endif ?>
                    </div>

                    <div class="card-body">
                        <div class="d-flex align-items-start gap-2 mb-2">
                            <div class="min-w-0 flex-fill">
                                <div class="fw-bold text-truncate" title="<?= esc($item['original_name'], 'attr') ?>">
                                    <?= esc($item['original_name']) ?>
                                </div>
                                <div class="text-secondary small">
                                    <?= esc($item['mime_type']) ?> · <?= esc($sizeKb) ?> KB
                                    <?php if ($item['width'] !== null && $item['height'] !== null): ?>
                                        · <?= esc((string) $item['width']) ?>×<?= esc((string) $item['height']) ?>
                                    <?php endif ?>
                                </div>
                            </div>
                        </div>

                        <?php if (($item['title'] ?? null) !== null): ?>
                            <div class="small mb-1">
                                <span class="text-secondary"><?= esc(lang('MCMS.common.title')) ?>:</span>
                                <?= esc($item['title']) ?>
                            </div>
                        <?php endif ?>

                        <?php if (($item['alt_text'] ?? null) !== null): ?>
                            <div class="small mb-2 text-truncate" title="<?= esc($item['alt_text'], 'attr') ?>">
                                <span class="text-secondary"><?= esc(lang('MCMS.media.altText')) ?>:</span>
                                <?= esc($item['alt_text']) ?>
                            </div>
                        <?php endif ?>

                        <div class="input-group input-group-sm">
                            <input
                                class="form-control font-monospace"
                                readonly
                                value="<?= esc($item['url']) ?>"
                                aria-label="URL"
                            >
                            <button
                                class="btn btn-outline-secondary btn-icon admin-icon-action" data-bs-toggle="tooltip" data-bs-container="body"
                                type="button"
                                data-copy-url="<?= esc($item['url'], 'attr') ?>"
                                data-copy-label="<?= esc(lang('MCMS.media.copyUrl'), 'attr') ?>"
                                data-copied-label="<?= esc(lang('MCMS.media.copied'), 'attr') ?>"
                                title="<?= esc(lang('MCMS.media.copyUrl'), 'attr') ?>"
                                aria-label="<?= esc(lang('MCMS.media.copyUrl'), 'attr') ?>"
                            >
                                <i class="ti ti-copy" aria-hidden="true"></i>
                            </button>
                            <a
                                class="btn btn-outline-secondary btn-icon admin-icon-action" data-bs-toggle="tooltip" data-bs-container="body"
                                href="<?= esc($item['url']) ?>"
                                target="_blank"
                                rel="noopener"
                                title="<?= esc(lang('MCMS.common.open'), 'attr') ?>"
                                aria-label="<?= esc(lang('MCMS.common.open'), 'attr') ?>"
                            >
                                <i class="ti ti-external-link" aria-hidden="true"></i>
                            </a>
                        </div>

                        <?php if ($canEdit): ?>
                            <details class="mt-3">
                                <summary class="text-secondary small" style="cursor:pointer">
                                    <i class="ti ti-pencil me-1"></i><?= esc(lang('MCMS.media.editMetadata')) ?>
                                </summary>

                                <form method="post" action="<?= site_url('admin/media/' . $item['id'] . '/metadata') ?>" class="mt-3">
                                    <?= csrf_field() ?>

                                    <div class="mb-2">
                                        <label class="form-label form-label-sm"><?= esc(lang('MCMS.common.title')) ?></label>
                                        <input
                                            class="form-control form-control-sm"
                                            name="title"
                                            maxlength="255"
                                            value="<?= esc((string) ($item['title'] ?? '')) ?>"
                                        >
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label form-label-sm"><?= esc(lang('MCMS.media.altText')) ?></label>
                                        <input
                                            class="form-control form-control-sm"
                                            name="alt_text"
                                            maxlength="255"
                                            value="<?= esc((string) ($item['alt_text'] ?? '')) ?>"
                                        >
                                    </div>

                                    <button class="btn btn-primary btn-sm w-100" type="submit">
                                        <i class="ti ti-device-floppy me-1"></i>
                                        <?= esc(lang('MCMS.media.saveMetadata')) ?>
                                    </button>
                                </form>
                            </details>
                        <?php endif ?>
                    </div>

                    <?php if ($canDelete): ?>
                        <div class="card-footer d-flex justify-content-end">
                            <form
                                method="post"
                                action="<?= site_url('admin/media/' . $item['id'] . '/delete') ?>"
                                onsubmit="return confirm(<?= esc(json_encode(lang('MCMS.media.deleteConfirm')), 'attr') ?>);"
                            >
                                <?= csrf_field() ?>
                                <button class="btn btn-outline-danger btn-sm btn-icon admin-icon-action" type="submit" aria-label="<?= esc(lang('MCMS.common.delete'), 'attr') ?>" title="<?= esc(lang('MCMS.common.delete'), 'attr') ?>" data-bs-toggle="tooltip" data-bs-container="body"><i class="ti ti-trash" aria-hidden="true"></i></button>
                            </form>
                        </div>
                    <?php endif ?>
                </div>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<script>
(() => {
    const toggle = document.getElementById('toggle-media-upload');
    const panel = document.getElementById('media-upload-panel');

    if (toggle && panel) {
        const label = toggle.querySelector('span');
        const icon = toggle.querySelector('i');
        const showLabel = <?= json_encode(lang('MCMS.media.uploadPanel'), JSON_UNESCAPED_UNICODE) ?>;
        const hideLabel = <?= json_encode(lang('MCMS.media.hideUpload'), JSON_UNESCAPED_UNICODE) ?>;

        toggle.addEventListener('click', () => {
            panel.hidden = ! panel.hidden;
            label.textContent = panel.hidden ? showLabel : hideLabel;
            icon.className = panel.hidden ? 'ti ti-upload me-1' : 'ti ti-x me-1';

            if (! panel.hidden) {
                document.getElementById('media_file')?.focus();
            }
        });
    }

    document.querySelectorAll('[data-copy-url]').forEach((button) => {
        button.addEventListener('click', async () => {
            const value = button.dataset.copyUrl || '';
            const copiedLabel = button.dataset.copiedLabel || '';
            const copyLabel = button.dataset.copyLabel || '';
            const icon = button.querySelector('i');

            try {
                await navigator.clipboard.writeText(value);
                button.title = copiedLabel;
                button.setAttribute('aria-label', copiedLabel);
                tabler.Tooltip.getInstance(button)?.setContent({'.tooltip-inner': copiedLabel});
                icon.className = 'ti ti-check';

                window.setTimeout(() => {
                    button.title = copyLabel;
                    button.setAttribute('aria-label', copyLabel);
                    tabler.Tooltip.getInstance(button)?.setContent({'.tooltip-inner': copyLabel});
                    icon.className = 'ti ti-copy';
                }, 1500);
            } catch (_) {
                const input = button.parentElement?.querySelector('input');
                input?.select();
            }
        });
    });
})();
</script>
<?php if ($lastPage > 1): ?>
    <nav class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3" aria-label="<?= esc(lang('MCMS.media.title'), 'attr') ?>">
        <div>
            <?php if ($page > 1): ?>
                <a class="btn btn-outline-secondary" href="<?= esc($pageUrl($page - 1), 'attr') ?>">
                    <i class="ti ti-chevron-left me-1"></i><?= esc(lang('MCMS.common.previousPage')) ?>
                </a>
            <?php endif ?>
        </div>
        <div class="text-secondary small"><?= (int) $page ?> / <?= (int) $lastPage ?></div>
        <div>
            <?php if ($page < $lastPage): ?>
                <a class="btn btn-outline-secondary" href="<?= esc($pageUrl($page + 1), 'attr') ?>">
                    <?= esc(lang('MCMS.common.nextPage')) ?><i class="ti ti-chevron-right ms-1"></i>
                </a>
            <?php endif ?>
        </div>
    </nav>
<?php endif ?>
<?= $this->endSection() ?>
