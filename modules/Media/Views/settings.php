<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md">
            <div class="page-pretitle"><?= esc(lang('MCMS.media.title')) ?></div>
            <h2 class="page-title"><?= esc(lang('MCMS.media.settings')) ?></h2>
        </div>
        <div class="col-12 col-md-auto ms-md-auto">
            <a class="btn btn-outline-secondary" href="<?= site_url('admin/media') ?>">
                <i class="ti ti-arrow-left me-1"></i><?= esc(lang('MCMS.media.back')) ?>
            </a>
        </div>
    </div>
</div>

<form method="post" class="row row-cards">
    <?= csrf_field() ?>

    <div class="col-xl-7">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="ti ti-adjustments me-2"></i><?= esc(lang('MCMS.media.settings')) ?>
                </h3>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="max_upload_mb"><?= esc(lang('MCMS.media.maximumUpload')) ?></label>
                        <div class="input-group">
                            <input
                                class="form-control"
                                id="max_upload_mb"
                                type="number"
                                name="max_upload_mb"
                                min="1"
                                max="64"
                                required
                                value="<?= esc((string) old('max_upload_mb', $maxUploadMb)) ?>"
                            >
                            <span class="input-group-text">MB</span>
                        </div>
                        <div class="form-hint"><?= esc(lang('MCMS.media.nginxLimit')) ?></div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="max_image_megapixels"><?= esc(lang('MCMS.media.maximumImage')) ?></label>
                        <div class="input-group">
                            <input
                                class="form-control"
                                id="max_image_megapixels"
                                type="number"
                                name="max_image_megapixels"
                                min="1"
                                max="200"
                                required
                                value="<?= esc((string) old('max_image_megapixels', $maxMegapixels)) ?>"
                            >
                            <span class="input-group-text">MP</span>
                        </div>
                        <div class="form-hint"><?= esc(lang('MCMS.media.imageLimitHint')) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="ti ti-file-type me-2"></i><?= esc(lang('MCMS.media.allowedTypes')) ?>
                </h3>
            </div>
            <div class="card-body">
                <?php foreach ($supported as $mime => $extension): ?>
                    <label class="form-check mb-3">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="allowed_mime_types[]"
                            value="<?= esc($mime) ?>"
                            <?= in_array($mime, old('allowed_mime_types', $allowed), true) ? 'checked' : '' ?>
                        >
                        <span class="form-check-label d-flex align-items-center justify-content-between gap-3 w-100">
                            <code><?= esc($mime) ?></code>
                            <span class="badge bg-secondary-lt">.<?= esc($extension) ?></span>
                        </span>
                    </label>
                <?php endforeach ?>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="d-flex justify-content-end">
            <button class="btn btn-primary" type="submit">
                <i class="ti ti-device-floppy me-1"></i><?= esc(lang('MCMS.media.saveSettings')) ?>
            </button>
        </div>
    </div>
</form>
<?= $this->endSection() ?>