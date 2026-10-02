<span class="sig-gallery-grid" role="list">
<?php foreach ($album['images'] as $image): ?><span class="sig-gallery-item" role="listitem">
    <?php if ($image['media'] !== null): ?><a class="sig-gallery-image" href="<?= esc(site_url(ltrim($image['media']['url'], '/')), 'attr') ?>" data-lightbox data-caption="<?= esc($image['caption'], 'attr') ?>" aria-label="<?= esc($image['caption'] ?: ($image['media']['alt_text'] ?: lang('Signature.original')), 'attr') ?>"><img src="<?= esc(site_url(ltrim($image['media']['thumbnail_url'], '/')), 'attr') ?>" alt="<?= esc($image['media']['alt_text'] ?: $image['caption'], 'attr') ?>" loading="lazy"><span aria-hidden="true">↗</span></a><?php else: ?><span class="sig-gallery-missing"><?= esc(lang('Galleries.Public.missing')) ?></span><?php endif ?>
    <?php if ($image['caption'] !== ''): ?><span class="sig-gallery-caption"><?= esc($image['caption']) ?></span><?php endif ?>
</span><?php endforeach ?>
</span>
