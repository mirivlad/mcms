<?= $this->extend('MCMS\\Themes\\DefaultTheme\\Views\\layout') ?>

<?= $this->section('content') ?>
<div class="container page-shell">
    <nav aria-label="<?= esc(lang('MCMS.themes.breadcrumbs'), 'attr') ?>" class="page-breadcrumbs">
        <a href="<?= site_url('/') ?>"><?= esc(lang('MCMS.themes.homeLink')) ?></a>
        <span aria-hidden="true">/</span>
        <span><?= esc($page['title']) ?></span>
    </nav>

    <article class="content-card article-card">
        <header class="article-header">
            <h1><?= esc($page['title']) ?></h1>
        </header>
        <div class="mcms-page-content">
            <?= $page['body'] ?>
        </div>
    </article>
<?php if (empty($isPreview) && service('mcmsModuleServices')->has('ratings.repository')): ?>
<?= view('MCMS\\Modules\\Ratings\\Views\\widget', ['targetType' => 'pages.page', 'targetId' => (string) $page['id']]) ?>
<?php endif ?>
<?php if (empty($isPreview) && service('mcmsModuleServices')->has('comments.repository')): ?>
    <?= view('MCMS\\Modules\\Comments\\Views\\widget', ['targetType' => 'pages.page', 'targetId' => (string) $page['id']]) ?>
<?php endif ?>
</div>
<?= $this->endSection() ?>
