<?= $this->extend('MCMS\\Themes\\Signature\\Views\\layout') ?>
<?= $this->section('content') ?>
<?php
$navigation = service('mcmsNavigation');
$news = $navigation->resolve('news.archive', 'index');
$gallery = $navigation->resolve('galleries.archive', 'index');
$contact = $navigation->resolve('feedback.contact', 'index');
$services = service('mcmsModuleServices');
$articles = $services->has('news.repository') ? $services->get('news.repository')->listPublished('', 3) : [];
$albums = $services->has('galleries.repository') ? $services->get('galleries.repository')->listPublished('', 3) : [];
?>
<section class="sig-hero"><div class="sig-container sig-hero-grid"><div class="sig-hero-copy"><p class="sig-eyebrow"><?= esc(lang('Signature.eyebrow')) ?></p><h1><?= nl2br(esc(lang('Signature.headline'))) ?><span class="sig-heading-dot" aria-hidden="true">.</span></h1><p class="sig-lead"><?= esc(lang('Signature.lead')) ?></p><div class="sig-hero-actions">
<?php if ($news !== null): ?><a class="sig-button" href="<?= site_url('news') ?>"><?= esc(lang('Signature.discover')) ?> <span aria-hidden="true">↗</span></a><?php elseif ($gallery !== null): ?><a class="sig-button" href="<?= site_url('galleries') ?>"><?= esc(lang('Galleries.Public.title')) ?> <span aria-hidden="true">↗</span></a><?php endif ?>
<?php if ($contact !== null): ?><a class="sig-text-link" href="<?= site_url('contact') ?>"><?= esc(lang('Feedback.Public.title')) ?> →</a><?php endif ?>
</div></div><div class="sig-hero-art" aria-hidden="true"><div class="sig-orbit sig-orbit-one"></div><div class="sig-orbit sig-orbit-two"></div><div class="sig-hero-monogram">m<span>·</span></div><span class="sig-art-caption">mCMS — Signature</span><span class="sig-art-number">01 / ∞</span></div></div></section>
<?php if ($articles !== []): ?><section class="sig-container sig-home-section"><header class="sig-section-heading sig-heading-row"><div><p class="sig-eyebrow"><?= esc(lang('News.Public.title')) ?></p><h2><?= esc(lang('Signature.latest')) ?></h2><p><?= esc(lang('Signature.latestLead')) ?></p></div><a class="sig-text-link" href="<?= site_url('news') ?>"><?= esc(lang('News.Public.title')) ?> →</a></header><?= view('MCMS\\Themes\\Signature\\Views\\news\\_cards', ['articles' => $articles, 'homeCards' => true], ['saveData' => false]) ?></section><?php endif ?>
<?php if ($albums !== []): ?><section class="sig-visual-section"><div class="sig-container"><header class="sig-section-heading sig-heading-row"><div><p class="sig-eyebrow"><?= esc(lang('Galleries.Public.title')) ?></p><h2><?= esc(lang('Signature.visual')) ?></h2><p><?= esc(lang('Signature.visualLead')) ?></p></div><a class="sig-text-link" href="<?= site_url('galleries') ?>"><?= esc(lang('Galleries.Public.back')) ?> →</a></header><?= view('MCMS\\Themes\\Signature\\Views\\galleries\\_cards', ['albums' => $albums, 'homeCards' => true], ['saveData' => false]) ?></div></section><?php endif ?>
<?= $this->endSection() ?>
