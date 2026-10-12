<?= $this->extend('MCMS\\Themes\\Depth\\Views\\layout') ?>
<?= $this->section('content') ?>
<div class="site-container site-reading-shell">
    <nav class="site-breadcrumbs" aria-label="<?= esc(lang('MCMS.themes.breadcrumbs'), 'attr') ?>"><a href="<?= site_url('/') ?>"><?= esc(lang('Depth.home')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= esc($page['title']) ?></span></nav>
    <article class="site-article"><header class="site-article-header"><p class="site-eyebrow"><?= esc($siteName) ?></p><h1><?= esc($page['title']) ?></h1></header><div class="site-prose mcms-page-content"><?= $page['body'] ?></div></article>
<?php if (empty($isPreview) && service('mcmsModuleServices')->has('comments.repository')): ?>
    <?= view('MCMS\\Modules\\Comments\\Views\\widget', ['targetType' => 'pages.page', 'targetId' => (string) $page['id']]) ?>
<?php endif ?>
</div>
<?= $this->endSection() ?>
