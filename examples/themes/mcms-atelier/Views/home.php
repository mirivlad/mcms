<?= $this->extend('MCMS\\Themes\\Atelier\\Views\\layout') ?>
<?= $this->section('content') ?>
<?php
$services = service('mcmsModuleServices');
$articles = $themeHome['show_news'] && $services->has('news.repository') ? $services->get('news.repository')->listPublished('', 3) : [];
$albums = $themeHome['show_galleries'] && $services->has('galleries.repository') ? $services->get('galleries.repository')->listPublished('', 3) : [];
$feature = $articles[0] ?? null;
$cover = $themeHome['image'] !== null ? site_url(ltrim($themeHome['image']['url'], '/')) : $themeAssets . 'images/hero.svg';
$buttonUrl = $themeHome['button_url'];
$buttonUrl = str_starts_with($buttonUrl, '/') ? site_url(ltrim($buttonUrl, '/')) : $buttonUrl;
?>
<section class="site-container atelier-home-grid">
    <article class="atelier-feature">
        <img class="atelier-feature-image" src="<?= esc($cover, 'attr') ?>" alt="" width="1536" height="1024" fetchpriority="high">
        <div class="atelier-feature-meta"><span><?= esc(\MCMS\Themes\Atelier\Views\Text::get('feature')) ?></span><?php if ($feature !== null): ?><time datetime="<?= esc($feature['published_at'], 'attr') ?>"><?= esc(date('d.m.Y', strtotime($feature['published_at']))) ?></time><?php endif ?></div>
        <h2><?php if ($feature !== null): ?><a href="<?= esc(site_url(ltrim($feature['url'], '/')), 'attr') ?>"><?= esc($feature['title']) ?></a><?php else: ?><?= esc(\MCMS\Themes\Atelier\Views\Text::get('coverTitle')) ?><?php endif ?></h2>
    </article>
    <div class="atelier-home-copy">
        <?php if ($themeHome['eyebrow'] !== ''): ?><p class="site-eyebrow"><?= esc($themeHome['eyebrow']) ?></p><?php endif ?>
        <h1><?= nl2br(esc($themeHome['title'])) ?></h1>
        <p class="atelier-home-lead"><?= nl2br(esc($themeHome['description'])) ?></p>
        <?php if ($buttonUrl !== '' && $themeHome['button_label'] !== ''): ?><a class="site-button" href="<?= esc($buttonUrl, 'attr') ?>"><?= esc($themeHome['button_label']) ?><span aria-hidden="true">↗</span></a><?php endif ?>
        <?php if (count($articles) > 1): ?><div class="atelier-story-list">
            <?php foreach (array_slice($articles, 1) as $article): ?><article><time class="site-eyebrow" datetime="<?= esc($article['published_at'], 'attr') ?>"><?= esc(date('d.m.Y', strtotime($article['published_at']))) ?></time><h2><a href="<?= esc(site_url(ltrim($article['url'], '/')), 'attr') ?>"><?= esc($article['title']) ?></a></h2></article><?php endforeach ?>
        </div><?php endif ?>
    </div>
</section>
<div id="home-content">
<?php if ($themeHome['intro_title'] !== '' || $themeHome['intro_text'] !== ''): ?><section class="atelier-home-band"><div class="site-container"><?php if ($themeHome['intro_title'] !== ''): ?><h2><?= nl2br(esc($themeHome['intro_title'])) ?></h2><?php endif ?><?php if ($themeHome['intro_text'] !== ''): ?><p><?= nl2br(esc($themeHome['intro_text'])) ?></p><?php endif ?></div></section><?php endif ?>
<?php if ($albums !== []): ?><section class="site-container site-home-section"><header class="site-section-heading site-heading-row"><div><p class="site-eyebrow"><?= esc(lang('Galleries.Public.title')) ?></p><h2><?= esc(\MCMS\Themes\Atelier\Views\Text::get('visual')) ?></h2></div><a class="site-text-link" href="<?= site_url('galleries') ?>"><?= esc(lang('Galleries.Public.back')) ?> →</a></header><?= view('MCMS\\Themes\\Atelier\\Views\\galleries\\_cards', ['albums' => $albums, 'homeCards' => true], ['saveData' => false]) ?></section><?php endif ?>
</div>
<?= $this->endSection() ?>
