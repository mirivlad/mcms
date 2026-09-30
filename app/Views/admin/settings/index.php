<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">Core</div>
            <h2 class="page-title">Settings</h2>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="post">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="site_name">Site name</label>
                <input class="form-control" id="site_name" name="site_name" maxlength="120" required value="<?= esc(old('site_name', (string) $siteName)) ?>">
            </div>
            <label class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="allow_registration" value="1" <?= $allowRegistration ? 'checked' : '' ?>>
                <span class="form-check-label">Allow public registration</span>
            </label>
            <button class="btn btn-primary" type="submit">Save settings</button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
