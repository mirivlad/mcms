<?= $this->extend('MCMS\\Themes\\DefaultTheme\\Views\\layout') ?>

<?php $documentTitle = (string) (($page['seo_title'] ?? null) ?: $page['title']); ?>

<?= $this->section('title') ?><?= esc($documentTitle) ?> · <?= esc($siteName) ?><?= $this->endSection() ?>

<?php if (($page['seo_description'] ?? null) !== null): ?>
    <?= $this->section('meta') ?>
    <meta name="description" content="<?= esc($page['seo_description'], 'attr') ?>">
    <?= $this->endSection() ?>
<?php endif ?>

<?= $this->section('content') ?>
<article class="container py-5 py-lg-6">
    <div class="mcms-content-shell">
        <header class="mb-4 mb-lg-5">
            <h1 class="display-5 fw-semibold mb-0"><?= esc($page['title']) ?></h1>
        </header>
        <div class="mcms-page-content">
            <?= $page['body'] ?>
        </div>
    </div>
</article>
<?= $this->endSection() ?>