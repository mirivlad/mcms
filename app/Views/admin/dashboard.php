<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">mCMS Core</div>
            <h2 class="page-title"><?= esc(lang('MCMS.dashboard.title')) ?></h2>
        </div>
    </div>
</div>

<div class="row row-cards">
    <div class="col-sm-6 col-lg-4">
        <div class="card">
            <div class="card-body">
                <div class="subheader"><?= esc(lang('MCMS.common.system')) ?></div>
                <div class="h3 mb-0"><?= esc(lang('MCMS.dashboard.running')) ?></div>
                <div class="text-secondary mt-2"><?= esc(lang('MCMS.dashboard.description')) ?></div>
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
