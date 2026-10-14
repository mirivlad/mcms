<?= $this->extend('MCMS\\Themes\\Signature\\Views\\layout') ?>
<?= $this->section('content') ?>
<div class="sig-container sig-reading-shell">
    <nav class="sig-breadcrumbs" aria-label="<?= esc(lang('MCMS.themes.breadcrumbs'), 'attr') ?>"><a href="<?= site_url('/') ?>"><?= esc(lang('Signature.home')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= esc($page['title']) ?></span></nav>
    <?= view('themes/content_actions', ['targetType' => 'pages.page', 'targetId' => (string) $page['id']]) ?>
    <article class="sig-article"><header class="sig-article-header"><p class="sig-eyebrow"><?= esc($siteName) ?></p><h1><?= esc($page['title']) ?></h1></header><div class="sig-prose mcms-page-content"><?= \MCMS\Core\Content\PublicContent::body((string) $page['body'], (string) $page['title']) ?></div></article>
    <?= view('themes/content_interactions', ['targetType' => 'pages.page', 'targetId' => (string) $page['id'], 'isPreview' => $isPreview ?? false]) ?>
</div>
<?= $this->endSection() ?>
