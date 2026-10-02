<?= $this->extend('MCMS\\Themes\\Signature\\Views\\layout') ?>
<?= $this->section('content') ?>
<div class="sig-container sig-reading-shell">
    <nav class="sig-breadcrumbs" aria-label="<?= esc(lang('MCMS.themes.breadcrumbs'), 'attr') ?>"><a href="<?= site_url('/') ?>"><?= esc(lang('Signature.home')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= esc($page['title']) ?></span></nav>
    <article class="sig-article"><header class="sig-article-header"><p class="sig-eyebrow"><?= esc($siteName) ?></p><h1><?= esc($page['title']) ?></h1></header><div class="sig-prose mcms-page-content"><?= $page['body'] ?></div></article>
</div>
<?= $this->endSection() ?>
