<?= $this->extend('MCMS\\Themes\\Atelier\\Views\\layout') ?>
<?= $this->section('content') ?>
<div class="site-container site-reading-shell">
    <nav class="site-breadcrumbs" aria-label="<?= esc(lang('MCMS.themes.breadcrumbs'), 'attr') ?>"><a href="<?= site_url('/') ?>"><?= esc(\MCMS\Themes\Atelier\Views\Text::get('home')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= esc($page['title']) ?></span></nav>
    <?= view('themes/content_actions', ['targetType' => 'pages.page', 'targetId' => (string) $page['id']]) ?>
    <article class="site-article"><header class="site-article-header"><p class="site-eyebrow"><?= esc($siteName) ?></p><h1><?= esc($page['title']) ?></h1></header><div class="site-prose mcms-page-content"><?= \MCMS\Core\Content\PublicContent::body((string) $page['body'], (string) $page['title']) ?></div></article>
    <?= view('themes/content_interactions', ['targetType' => 'pages.page', 'targetId' => (string) $page['id'], 'isPreview' => $isPreview ?? false]) ?>
</div>
<?= $this->endSection() ?>
