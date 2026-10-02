<span class="mcms-gallery-collection mcms-gallery-<?= esc($display, 'attr') ?>" role="group" aria-label="<?= esc($album['title'], 'attr') ?>" <?php if ($display === 'carousel'): ?>data-gallery-carousel aria-roledescription="<?= esc(lang('Galleries.Public.carousel'), 'attr') ?>"<?php endif ?> data-gallery data-gallery-title="<?= esc($album['title'], 'attr') ?>" data-gallery-id="<?= (int) $album['id'] ?>">
    <span class="mcms-gallery-collection-heading">
        <a href="<?= esc(site_url(ltrim($album['url'], '/')), 'attr') ?>"><?= esc($album['title']) ?> ↗</a>
        <?php if ($display === 'carousel' && count($album['images']) > 1): ?>
        <span class="mcms-gallery-carousel-controls" data-carousel-controls hidden>
            <button type="button" data-carousel-prev aria-label="<?= esc(lang('Galleries.Public.previousSlide'), 'attr') ?>">←</button>
            <button type="button" data-carousel-next aria-label="<?= esc(lang('Galleries.Public.nextSlide'), 'attr') ?>">→</button>
        </span>
        <?php endif ?>
    </span>
    <span class="mcms-gallery-collection-items" role="list" <?= $display === 'carousel' ? 'data-carousel-track' : '' ?>>
    <?php foreach ($album['images'] as $image): ?>
        <span class="mcms-gallery-card" role="listitem">
            <?php if ($image['media'] !== null): ?>
            <a class="mcms-gallery-card-image" href="<?= esc(site_url(ltrim($image['media']['url'], '/')), 'attr') ?>" data-lightbox data-caption="<?= esc($image['caption'], 'attr') ?>" target="_blank" rel="noopener" aria-label="<?= esc($image['caption'] ?: ($image['media']['alt_text'] ?: lang('Galleries.Public.original')), 'attr') ?>">
                <img src="<?= esc(site_url(ltrim($image['media']['thumbnail_url'], '/')), 'attr') ?>" alt="<?= esc($image['media']['alt_text'] ?: $image['caption'], 'attr') ?>" loading="lazy" decoding="async">
            </a>
            <?php else: ?><span class="mcms-gallery-card-missing"><?= esc(lang('Galleries.Public.missing')) ?></span><?php endif ?>
            <?php if ($image['caption'] !== ''): ?><span class="mcms-gallery-card-caption"><?= esc($image['caption']) ?></span><?php endif ?>
        </span>
    <?php endforeach ?>
    <?php if ($album['images'] === []): ?><span role="listitem"><?= esc(lang('Galleries.Public.noImages')) ?></span><?php endif ?>
    </span>
</span>
