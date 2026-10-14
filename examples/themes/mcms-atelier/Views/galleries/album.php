<?= $this->extend('MCMS\\Themes\\Atelier\\Views\\layout') ?>
<?= $this->section('content') ?>
<div class="site-container site-collection-shell"><nav class="site-breadcrumbs" aria-label="<?= esc(lang('MCMS.themes.breadcrumbs'), 'attr') ?>"><a href="<?= site_url('galleries') ?>"><?= esc(lang('Galleries.Public.back')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= esc($album['title']) ?></span></nav>
<?= view('themes/content_actions', ['targetType' => 'galleries.album', 'targetId' => (string) $album['id']]) ?>
<header class="site-section-heading"><p class="site-eyebrow"><?= esc(\MCMS\Themes\Atelier\Views\Text::get('imageCount')) ?> · <?= count($album['images']) ?></p><h1><?= esc($album['title']) ?></h1><?php if ($album['description'] !== ''): ?><p class="site-lead"><?= nl2br(esc($album['description'])) ?></p><?php endif ?></header>
<section data-gallery data-gallery-title="<?= esc($album['title'], 'attr') ?>" aria-label="<?= esc($album['title'], 'attr') ?>"><?= view('MCMS\\Themes\\Atelier\\Views\\galleries\\_images', ['album' => $album]) ?></section>
</div>
<?= $this->endSection() ?>
