<?= $this->extend($accountThemeLayout ?? 'auth/standalone_layout') ?>
<?= $this->section('content') ?>
<div class="mcms-account-page<?= ! empty($accountDashboard) ? ' mcms-account-page--dashboard' : '' ?>">
    <div class="mcms-account-content">
        <nav class="mcms-account-breadcrumbs" aria-label="<?= esc(lang('MCMS.themes.breadcrumbs'), 'attr') ?>">
            <a href="<?= site_url('/') ?>"><?= esc(lang('MCMS.themes.homeLink')) ?></a>
            <span aria-hidden="true">/</span>
            <span aria-current="page"><?= esc($accountPageTitle ?? lang('MCMS.account.title')) ?></span>
        </nav>
        <div class="<?= ! empty($accountDashboard) ? 'mcms-account-dashboard-shell' : 'mcms-account-card' ?>">
            <?php foreach (['message' => 'success', 'error' => 'danger'] as $key => $color): ?>
                <?php if (session()->getFlashdata($key)): ?>
                    <div class="alert alert-<?= $color ?>" role="status"><?= esc((string) session()->getFlashdata($key)) ?></div>
                <?php endif ?>
            <?php endforeach ?>
            <?php if ($errors = session()->getFlashdata('errors')): ?>
                <div class="alert alert-danger" role="alert">
                    <?php foreach ((array) $errors as $error): ?>
                        <p class="mb-1"><?= esc((string) $error) ?></p>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
            <?= $this->renderSection('main') ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
