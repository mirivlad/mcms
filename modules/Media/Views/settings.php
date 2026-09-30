<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">Media</div>
            <h2 class="page-title">Settings</h2>
        </div>
        <div class="col-auto ms-auto">
            <a class="btn btn-link" href="<?= site_url('admin/media') ?>">Back to Media</a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="post">
            <?= csrf_field() ?>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="max_upload_mb">Maximum upload size</label>
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
                    <div class="form-hint">The nginx hard limit is currently 64 MB.</div>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label" for="max_image_megapixels">Maximum decoded image size</label>
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
                    <div class="form-hint">Protects the application from unexpectedly huge decoded images.</div>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Allowed file types</label>
                <?php foreach ($supported as $mime => $extension): ?>
                    <label class="form-check mb-2">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="allowed_mime_types[]"
                            value="<?= esc($mime) ?>"
                            <?= in_array($mime, old('allowed_mime_types', $allowed), true) ? 'checked' : '' ?>
                        >
                        <span class="form-check-label">
                            <code><?= esc($mime) ?></code>
                            <span class="text-secondary">.<?= esc($extension) ?></span>
                        </span>
                    </label>
                <?php endforeach ?>
            </div>

            <button class="btn btn-primary" type="submit">Save Media settings</button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
