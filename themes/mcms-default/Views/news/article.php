<?= $this->extend('MCMS\\Themes\\DefaultTheme\\Views\\layout') ?>

<?= $this->section('content') ?>
<?php $publicLead = \MCMS\Core\Content\PublicContent::excerpt(
    (string) ($article['excerpt'] ?? ''), (string) ($article['body'] ?? ''), (string) $article['title'],
); ?>
<div class="container page-shell">
    <nav aria-label="<?= esc(lang('MCMS.themes.breadcrumbs'), 'attr') ?>" class="page-breadcrumbs">
        <a href="<?= site_url('/') ?>"><?= esc(lang('MCMS.themes.homeLink')) ?></a>
        <span aria-hidden="true">/</span>
        <a href="<?= site_url('news') ?>"><?= esc(lang('News.Public.backToNews')) ?></a>
        <span aria-hidden="true">/</span>
        <span><?= esc($article['title']) ?></span>
    </nav>
    <?= view('themes/content_actions', ['targetType' => 'news.article', 'targetId' => (string) $article['id']]) ?>
    <article class="content-card article-card news-article">
        <header class="article-header">
            <time class="news-date" datetime="<?= esc((string) $article['published_at'], 'attr') ?>">
                <?= esc(date('d.m.Y H:i', strtotime((string) $article['published_at']))) ?>
            </time>
            <?= view('MCMS\\Modules\\News\\Views\\taxonomy', ['article' => $article]) ?>
<h1><?= esc($article['title']) ?></h1>
            <?php if ($publicLead !== ''): ?>
                <p class="news-article-lead"><?= esc($publicLead) ?></p>
            <?php endif ?>
        </header>

        <?php if (($article['featured_image_url'] ?? null) !== null): ?>
            <img class="news-article-image" src="<?= esc(site_url(ltrim($article['featured_image_url'], '/')), 'attr') ?>" alt="">
        <?php endif ?>

        <div class="mcms-page-content">
            <?= \MCMS\Core\Content\PublicContent::body((string) $article['body'], (string) $article['title']) ?>
        </div>
    </article>
    <?= view('themes/content_interactions', ['targetType' => 'news.article', 'targetId' => (string) $article['id'], 'isPreview' => $isPreview ?? false]) ?>
</div>
<?= $this->endSection() ?>
