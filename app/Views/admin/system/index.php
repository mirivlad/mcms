<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">Core</div>
            <h2 class="page-title">System</h2>
        </div>
    </div>
</div>

<div class="row row-cards">
    <div class="col-md-6">
        <div class="card"><div class="card-body">
            <h3 class="card-title">Runtime</h3>
            <dl class="row mb-0">
                <dt class="col-5">PHP</dt><dd class="col-7"><?= esc($phpVersion) ?></dd>
                <dt class="col-5">CodeIgniter</dt><dd class="col-7"><?= esc($ciVersion) ?></dd>
                <dt class="col-5">Environment</dt><dd class="col-7"><?= esc($environment) ?></dd>
            </dl>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card"><div class="card-body">
            <h3 class="card-title">Modules</h3>
            <dl class="row mb-0">
                <dt class="col-7">Available on disk</dt><dd class="col-5"><?= esc((string) $availableCount) ?></dd>
                <dt class="col-7">Enabled</dt><dd class="col-5"><?= esc((string) $enabledCount) ?></dd>
            </dl>
        </div></div>
    </div>
</div>
<?= $this->endSection() ?>
