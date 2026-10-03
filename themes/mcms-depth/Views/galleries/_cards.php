<?php $headingTag = ($homeCards ?? false) ? 'h3' : 'h2'; ?>
<div class="site-album-grid">
<?php foreach ($albums as $album): ?><article class="site-album-card"><a href="<?= esc(site_url(ltrim($album['url'], '/')), 'attr') ?>">
    <div class="site-album-image"><?php if ($album['cover'] !== null): ?><img src="<?= esc(site_url(ltrim($album['cover']['thumbnail_url'], '/')), 'attr') ?>" alt="<?= esc($album['cover']['alt_text'] ?? '', 'attr') ?>" loading="lazy"><?php else: ?><span class="site-album-placeholder" aria-hidden="true">↗</span><?php endif ?></div>
    <div class="site-album-body"><p class="site-eyebrow"><?= esc(lang('Depth.imageCount')) ?> · <?= count($album['images']) ?></p><<?= $headingTag ?>><?= esc($album['title']) ?></<?= $headingTag ?>><?php if ($album['description'] !== ''): ?><p><?= esc(mb_substr($album['description'], 0, 180)) ?></p><?php endif ?><span class="site-story-arrow" aria-hidden="true">↗</span></div>
</a></article><?php endforeach ?>
</div>
