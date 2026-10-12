<?= $this->extend('MCMS\\Themes\\Signature\\Views\\layout') ?>
<?= $this->section('content') ?>
<div class="sig-container sig-reading-shell">
    <nav class="sig-breadcrumbs" aria-label="<?= esc(lang('MCMS.themes.breadcrumbs'), 'attr') ?>"><a href="<?= site_url('/') ?>"><?= esc(lang('Signature.home')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= esc($page['title']) ?></span></nav>
    <article class="sig-article"><header class="sig-article-header"><p class="sig-eyebrow"><?= esc($siteName) ?></p><h1><?= esc($page['title']) ?></h1></header><div class="sig-prose mcms-page-content"><?= $page['body'] ?></div></article>
<?php if (empty($isPreview) && service('mcmsModuleServices')->has('ratings.repository')): ?>
<?= view('MCMS\\Modules\\Ratings\\Views\\widget', ['targetType' => 'pages.page', 'targetId' => (string) $page['id']]) ?>
<?php endif ?>
<?php if (empty($isPreview) && service('mcmsModuleServices')->has('comments.repository')): ?>
    <?= view('MCMS\\Modules\\Comments\\Views\\widget', ['targetType' => 'pages.page', 'targetId' => (string) $page['id']]) ?>
<?php endif ?>
</div>
<?= $this->endSection() ?>
