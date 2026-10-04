<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<h1 class="page-title mb-4"><?= esc(lang('Catalogue.manageStore')) ?></h1>
<div class="row g-3">
    <div class="col-md-6">
        <a class="card card-link" href="<?= site_url('admin/catalogue/categories') ?>">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="ti ti-folders fs-1" aria-hidden="true"></i>
                <h2 class="h3 mb-0"><?= esc(lang('Catalogue.manageCategories')) ?></h2>
            </div>
        </a>
    </div>
    <div class="col-md-6">
        <a class="card card-link" href="<?= site_url('admin/catalogue/products') ?>">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="ti ti-package fs-1" aria-hidden="true"></i>
                <h2 class="h3 mb-0"><?= esc(lang('Catalogue.manageProducts')) ?></h2>
            </div>
        </a>
    </div>
</div>
<?= $this->endSection() ?>
