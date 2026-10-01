<?= $this->extend('MCMS\\Themes\\DefaultTheme\\Views\\layout') ?>

<?= $this->section('content') ?>
<section class="hero">
    <div class="container">
        <div class="hero-panel">
            <div class="hero-kicker"><?= esc(lang('MCMS.themes.defaultHomeKicker')) ?></div>
            <h1><?= esc($siteName) ?></h1>
            <p><?= esc(sprintf(lang('MCMS.themes.defaultHomeLead'), $siteName)) ?></p>
        </div>
    </div>
</section>

<section class="home-note">
    <div class="container">
        <div class="content-card">
            <h2><?= esc(lang('MCMS.themes.defaultHomeReadyTitle')) ?></h2>
            <p class="mb-0"><?= esc(lang('MCMS.themes.defaultHomeReadyText')) ?></p>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
