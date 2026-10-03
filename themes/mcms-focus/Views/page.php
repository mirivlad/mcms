<?= $this->extend('MCMS\\Themes\\Focus\\Views\\layout') ?>
<?= $this->section('content') ?>
<div class="site-container site-reading-shell">
    <nav class="site-breadcrumbs" aria-label="<?= esc(lang('MCMS.themes.breadcrumbs'), 'attr') ?>"><a href="<?= site_url('/') ?>"><?= esc(lang('Focus.home')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= esc($page['title']) ?></span></nav>
    <article class="site-article"><header class="site-article-header"><p class="site-eyebrow"><?= esc($siteName) ?></p><h1><?= esc($page['title']) ?></h1></header><div class="site-prose mcms-page-content"><?= $page['body'] ?></div></article>
</div>
<?= $this->endSection() ?>
