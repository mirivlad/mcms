<?= $this->extend('MCMS\\Themes\\Signature\\Views\\layout') ?>
<?= $this->section('content') ?>
<div class="sig-container sig-collection-shell"><header class="sig-section-heading"><p class="sig-eyebrow"><?= esc($siteName) ?></p><h1><?= esc(lang('News.Public.title')) ?><span class="sig-heading-dot" aria-hidden="true">.</span></h1><p class="sig-lead"><?= esc(lang('Signature.newsLead')) ?></p></header>
<?php if ($articles === []): ?><div class="sig-empty"><h2><?= esc(lang('Signature.emptyTitle')) ?></h2><p><?= esc(lang('News.Public.empty')) ?></p></div><?php else: ?><?= view('MCMS\\Themes\\Signature\\Views\\news\\_cards', ['articles' => $articles]) ?><?php endif ?>
<?php if ($lastPage > 1): ?><nav class="sig-pagination" aria-label="<?= esc(lang('News.Public.title'), 'attr') ?>"><span><?php if ($page > 1): ?><a class="sig-button sig-button-secondary" href="<?= site_url('news') ?>?page=<?= $page - 1 ?>">← <?= esc(lang('News.Public.newer')) ?></a><?php endif ?></span><span><?= esc(lang('Signature.page')) ?> <?= (int) $page ?> / <?= (int) $lastPage ?></span><span><?php if ($page < $lastPage): ?><a class="sig-button sig-button-secondary" href="<?= site_url('news') ?>?page=<?= $page + 1 ?>"><?= esc(lang('News.Public.older')) ?> →</a><?php endif ?></span></nav><?php endif ?>
</div>
<?= $this->endSection() ?>
