<?= $this->extend('MCMS\\Themes\\Focus\\Views\\layout') ?>
<?= $this->section('content') ?>
<div class="site-container site-reading-shell">
    <nav class="site-breadcrumbs" aria-label="<?= esc(lang('MCMS.themes.breadcrumbs'), 'attr') ?>"><a href="<?= site_url('/') ?>"><?= esc(lang('Focus.home')) ?></a><span aria-hidden="true">/</span><a href="<?= site_url('news') ?>"><?= esc(lang('News.Public.title')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= esc($article['title']) ?></span></nav>
    <article class="site-article"><header class="site-article-header"><time class="site-eyebrow" datetime="<?= esc($article['published_at'], 'attr') ?>"><?= esc(date('d.m.Y', strtotime($article['published_at']))) ?></time><?= view('MCMS\\Modules\\News\\Views\\taxonomy', ['article' => $article]) ?>
<h1><?= esc($article['title']) ?></h1><?php if (! empty($article['excerpt'])): ?><p class="site-lead"><?= esc($article['excerpt']) ?></p><?php endif ?></header>
    <?php if ($article['featured_image_url'] !== null): ?><img class="site-article-cover" src="<?= esc(site_url(ltrim($article['featured_image_url'], '/')), 'attr') ?>" alt=""><?php endif ?>
    <div class="site-prose mcms-page-content"><?= $article['body'] ?></div></article>
<?php if (empty($isPreview) && service('mcmsModuleServices')->has('comments.repository')): ?>
    <?= view('MCMS\\Modules\\Comments\\Views\\widget', ['targetType' => 'news.article', 'targetId' => (string) $article['id']]) ?>
<?php endif ?>
</div>
<?= $this->endSection() ?>
