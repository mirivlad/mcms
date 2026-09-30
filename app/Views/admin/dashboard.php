<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">mCMS Core</div>
            <h2 class="page-title">Dashboard</h2>
        </div>
    </div>
</div>

<div class="row row-cards">
    <div class="col-sm-6 col-lg-4">
        <div class="card">
            <div class="card-body">
                <div class="subheader">System</div>
                <div class="h3 mb-0">mCMS is running</div>
                <div class="text-secondary mt-2">Modules extend this dashboard without changing the Core layout.</div>
            </div>
        </div>
    </div>

    <?php foreach ($widgets ?? [] as $widget): ?>
        <div class="col-sm-6 col-lg-4">
            <?= view($widget['view'], $widget['data']) ?>
        </div>
    <?php endforeach ?>
</div>
<?= $this->endSection() ?>
