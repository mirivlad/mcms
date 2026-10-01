<?= $this->extend('MCMS\\Themes\\DefaultTheme\\Views\\layout') ?>

<?= $this->section('title') ?><?= esc($siteName) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<section class="mcms-hero">
    <div class="container py-5 py-lg-6">
        <div class="row align-items-center g-5">
            <div class="col-lg-8">
                <div class="text-uppercase small fw-semibold text-secondary mb-3">
                    <?= esc(lang('DefaultTheme.Home.eyebrow')) ?>
                </div>
                <h1 class="display-4 fw-semibold mb-3"><?= esc($siteName) ?></h1>
                <p class="lead mb-3"><?= esc(lang('DefaultTheme.Home.lead')) ?></p>
                <p class="text-secondary fs-5 mb-0 mcms-copy-width">
                    <?= esc(lang('DefaultTheme.Home.description')) ?>
                </p>
            </div>
        </div>
    </div>
</section>
<?= $this->endSection() ?>