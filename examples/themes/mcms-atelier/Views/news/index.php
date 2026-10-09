<?= $this->extend('MCMS\\Themes\\Atelier\\Views\\layout') ?>
<?= $this->section('content') ?>
<div class="site-container site-collection-shell"><header class="site-section-heading"><p class="site-eyebrow"><?= esc($siteName) ?></p><h1><?= esc(lang('News.Public.title')) ?><span class="site-heading-dot" aria-hidden="true">.</span></h1><p class="site-lead"><?= esc(\MCMS\Themes\Atelier\Views\Text::get('newsLead')) ?></p></header>
<?= view('MCMS\\Modules\\News\\Views\\public_filters') ?>
<?php if ($articles === []): ?><div class="site-empty"><h2><?= esc(\MCMS\Themes\Atelier\Views\Text::get('emptyTitle')) ?></h2><p><?= esc(lang('News.Public.empty')) ?></p></div><?php else: ?><?= view('MCMS\\Themes\\Atelier\\Views\\news\\_cards', ['articles' => $articles]) ?><?php endif ?>
<?php if ($lastPage > 1): ?><nav class="site-pagination" aria-label="<?= esc(lang('News.Public.title'), 'attr') ?>"><span><?php if ($page > 1): ?><a class="site-button site-button-secondary" href="<?= esc(isset($newsPageUrl) ? $newsPageUrl($page - 1) : site_url('news') . '?page=' . ($page - 1), 'attr') ?>">← <?= esc(lang('News.Public.newer')) ?></a><?php endif ?></span><span><?= esc(\MCMS\Themes\Atelier\Views\Text::get('page')) ?> <?= (int) $page ?> / <?= (int) $lastPage ?></span><span><?php if ($page < $lastPage): ?><a class="site-button site-button-secondary" href="<?= esc(isset($newsPageUrl) ? $newsPageUrl($page + 1) : site_url('news') . '?page=' . ($page + 1), 'attr') ?>"><?= esc(lang('News.Public.older')) ?> →</a><?php endif ?></span></nav><?php endif ?>
</div>
<?= $this->endSection() ?>
