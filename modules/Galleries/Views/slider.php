<?php $slides = array_values(array_filter($album['images'], static fn (array $image): bool => $image['media'] !== null)); ?>
<span class="mcms-gallery-slider" role="group" aria-roledescription="<?= esc(lang('Galleries.Public.slider'), 'attr') ?>" aria-label="<?= esc($album['title'], 'attr') ?>" data-gallery-slider data-gallery-id="<?= (int) $album['id'] ?>">
    <span class="mcms-gallery-slides">
    <?php foreach ($slides as $index => $image): ?>
        <span class="mcms-gallery-slide" role="group" aria-roledescription="<?= esc(lang('Galleries.Public.slide'), 'attr') ?>" aria-label="<?= esc(sprintf(lang('Galleries.Public.slideNumber'), $index + 1, count($slides)), 'attr') ?>" data-slide <?= $index > 0 ? 'hidden' : '' ?>>
            <img src="<?= esc(site_url(ltrim($image['media']['url'], '/')), 'attr') ?>" alt="<?= esc($image['media']['alt_text'] ?: $image['caption'], 'attr') ?>" <?= $index > 0 ? 'loading="lazy"' : '' ?> decoding="async">
            <?php if ($image['caption'] !== ''): ?><span class="mcms-gallery-slide-caption"><?= esc($image['caption']) ?></span><?php endif ?>
        </span>
    <?php endforeach ?>
    <?php if ($slides === []): ?><span class="mcms-gallery-slide-empty"><?= esc(lang('Galleries.Public.noImages')) ?></span><?php endif ?>
    </span>
    <span class="mcms-gallery-slider-footer">
        <a href="<?= esc(site_url(ltrim($album['url'], '/')), 'attr') ?>"><?= esc($album['title']) ?> ↗</a>
        <?php if (count($slides) > 1): ?>
        <span class="mcms-gallery-slider-controls" data-slider-controls hidden>
            <button type="button" data-slider-prev aria-label="<?= esc(lang('Galleries.Public.previousSlide'), 'attr') ?>">←</button>
            <span class="mcms-gallery-slider-count" aria-live="polite" aria-atomic="true" data-slider-count><?= esc(sprintf(lang('Galleries.Public.slideNumber'), 1, count($slides))) ?></span>
            <button type="button" data-slider-next aria-label="<?= esc(lang('Galleries.Public.nextSlide'), 'attr') ?>">→</button>
        </span>
        <?php endif ?>
    </span>
    <?php if (count($slides) > 1 && count($slides) <= 12): ?>
    <span class="mcms-gallery-slider-indicators" data-slider-indicators hidden>
        <?php foreach ($slides as $index => $image): ?><button type="button" data-slider-go="<?= $index ?>" aria-label="<?= esc(sprintf(lang('Galleries.Public.slideNumber'), $index + 1, count($slides)), 'attr') ?>" <?= $index === 0 ? 'aria-current="true"' : '' ?>><span aria-hidden="true"></span></button><?php endforeach ?>
    </span>
    <?php endif ?>
</span>
