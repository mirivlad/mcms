<?= $this->extend('MCMS\\Themes\\Signature\\Views\\layout') ?>
<?= $this->section('content') ?>
<div class="sig-container sig-collection-shell"><header class="sig-section-heading"><p class="sig-eyebrow"><?= esc($siteName) ?></p><h1><?= esc(lang('Galleries.Public.title')) ?><span class="sig-heading-dot" aria-hidden="true">.</span></h1><p class="sig-lead"><?= esc(lang('Signature.galleryLead')) ?></p></header>
<?php if ($albums === []): ?><div class="sig-empty"><h2><?= esc(lang('Signature.emptyTitle')) ?></h2><p><?= esc(lang('Galleries.Public.empty')) ?></p></div><?php else: ?><?= view('MCMS\\Themes\\Signature\\Views\\galleries\\_cards', ['albums' => $albums]) ?><?php endif ?>
<?php if ($lastPage > 1): ?><nav class="sig-pagination" aria-label="<?= esc(lang('Galleries.Public.title'), 'attr') ?>"><span><?php if ($page > 1): ?><a class="sig-button sig-button-secondary" href="<?= site_url('galleries') ?>?page=<?= $page - 1 ?>">← <?= esc(lang('MCMS.common.previousPage')) ?></a><?php endif ?></span><span><?= esc(lang('Signature.page')) ?> <?= (int) $page ?> / <?= (int) $lastPage ?></span><span><?php if ($page < $lastPage): ?><a class="sig-button sig-button-secondary" href="<?= site_url('galleries') ?>?page=<?= $page + 1 ?>"><?= esc(lang('MCMS.common.nextPage')) ?> →</a><?php endif ?></span></nav><?php endif ?>
</div>
<?= $this->endSection() ?>
