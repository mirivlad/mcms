<?= $this->extend('MCMS\\Themes\\Depth\\Views\\layout') ?>
<?= $this->section('content') ?>
<?php
$services = service('mcmsModuleServices');
$articles = $themeHome['show_news'] && $services->has('news.repository') ? $services->get('news.repository')->listPublished('', 2) : [];
$albums = $themeHome['show_galleries'] && $services->has('galleries.repository') ? $services->get('galleries.repository')->listPublished('', 3) : [];
$cover = $themeHome['image'] !== null ? site_url(ltrim($themeHome['image']['url'], '/')) : $themeAssets . 'images/hero.webp';
$buttonUrl = $themeHome['button_url'];
$buttonUrl = str_starts_with($buttonUrl, '/') ? site_url(ltrim($buttonUrl, '/')) : $buttonUrl;
?>
<section class="depth-hero">
    <img class="depth-hero-image" src="<?= esc($cover, 'attr') ?>" alt="" fetchpriority="high" width="1672" height="941">
    <div class="site-container depth-hero-content">
        <?php if ($themeHome['eyebrow'] !== ''): ?><p class="site-eyebrow"><?= esc($themeHome['eyebrow']) ?></p><?php endif ?>
        <h1><?= nl2br(esc($themeHome['title'])) ?></h1>
        <?php if ($themeHome['description'] !== ''): ?><p class="depth-hero-lead"><?= nl2br(esc($themeHome['description'])) ?></p><?php endif ?>
        <?php if ($buttonUrl !== '' && $themeHome['button_label'] !== ''): ?><a class="site-button" href="<?= esc($buttonUrl, 'attr') ?>"><?= esc($themeHome['button_label']) ?><span aria-hidden="true">↗</span></a><?php endif ?>
    </div>
    <div class="site-container depth-hero-caption"><span><?= esc(lang('Depth.heroCaption')) ?></span><a href="#home-content"><?= esc(lang('Depth.more')) ?> ↓</a></div>
</section>
<section class="site-container depth-home-content" id="home-content">
    <div class="depth-home-intro"><?php if ($themeHome['intro_title'] !== ''): ?><h2><?= nl2br(esc($themeHome['intro_title'])) ?></h2><?php endif ?><?php if ($themeHome['intro_text'] !== ''): ?><p><?= nl2br(esc($themeHome['intro_text'])) ?></p><?php endif ?></div>
    <?php if ($articles !== []): ?><div class="depth-home-stories"><?= view('MCMS\\Themes\\Depth\\Views\\news\\_cards', ['articles' => $articles, 'homeCards' => true], ['saveData' => false]) ?></div><?php endif ?>
</section>
<?php if ($albums !== []): ?>
<section class="site-container site-home-section"><header class="site-section-heading site-heading-row"><div><p class="site-eyebrow"><?= esc(lang('Galleries.Public.title')) ?></p><h2><?= esc(lang('Depth.visual')) ?></h2></div><a class="site-text-link" href="<?= site_url('galleries') ?>"><?= esc(lang('Galleries.Public.back')) ?> →</a></header><?= view('MCMS\\Themes\\Depth\\Views\\galleries\\_cards', ['albums' => $albums, 'homeCards' => true], ['saveData' => false]) ?></section>
<?php endif ?>
<?= $this->endSection() ?>
