<?php $headingTag = ($homeCards ?? false) ? 'h3' : 'h2'; ?>
<div class="sig-album-grid">
<?php foreach ($albums as $album): ?><article class="sig-album-card"><a href="<?= esc(site_url(ltrim($album['url'], '/')), 'attr') ?>">
    <div class="sig-album-image"><?php if ($album['cover'] !== null): ?><img src="<?= esc(site_url(ltrim($album['cover']['thumbnail_url'], '/')), 'attr') ?>" alt="<?= esc($album['cover']['alt_text'] ?? '', 'attr') ?>" loading="lazy"><?php else: ?><span class="sig-mark" aria-hidden="true">m<span>·</span></span><?php endif ?></div>
    <div class="sig-album-body"><p class="sig-eyebrow"><?= esc(lang('Signature.imageCount')) ?> · <?= count($album['images']) ?></p><<?= $headingTag ?>><?= esc($album['title']) ?></<?= $headingTag ?>><?php if ($album['description'] !== ''): ?><p><?= esc(mb_substr($album['description'], 0, 180)) ?></p><?php endif ?><span class="sig-story-arrow" aria-hidden="true">↗</span></div>
</a></article><?php endforeach ?>
</div>
