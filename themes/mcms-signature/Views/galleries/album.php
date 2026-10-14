<?= $this->extend('MCMS\\Themes\\Signature\\Views\\layout') ?>
<?= $this->section('content') ?>
<div class="sig-container sig-collection-shell"><nav class="sig-breadcrumbs" aria-label="<?= esc(lang('MCMS.themes.breadcrumbs'), 'attr') ?>"><a href="<?= site_url('galleries') ?>"><?= esc(lang('Galleries.Public.back')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= esc($album['title']) ?></span></nav>
<?= view('themes/content_actions', ['targetType' => 'galleries.album', 'targetId' => (string) $album['id']]) ?>
<header class="sig-section-heading"><p class="sig-eyebrow"><?= esc(lang('Signature.imageCount')) ?> · <?= count($album['images']) ?></p><h1><?= esc($album['title']) ?></h1><?php if ($album['description'] !== ''): ?><p class="sig-lead"><?= nl2br(esc($album['description'])) ?></p><?php endif ?></header>
<section data-gallery data-gallery-title="<?= esc($album['title'], 'attr') ?>" aria-label="<?= esc($album['title'], 'attr') ?>"><?= view('MCMS\\Themes\\Signature\\Views\\galleries\\_images', ['album' => $album]) ?></section>
</div>
<?= $this->endSection() ?>
