<span class="mcms-gallery-embed d-block my-4" role="group" aria-label="<?= esc($album['title'], 'attr') ?>" data-gallery-id="<?= (int) $album['id'] ?>">
    <a class="d-block fw-semibold mb-3" href="<?= esc(site_url(ltrim($album['url'], '/')), 'attr') ?>"><?= esc($album['title']) ?> →</a>
    <span class="row g-3" role="list">
        <?php foreach ($album['images'] as $image): ?><span class="col-sm-6 col-lg-4" role="listitem">
            <?php if ($image['media'] !== null): ?><a href="<?= esc(site_url(ltrim($image['media']['url'], '/')), 'attr') ?>" target="_blank" rel="noopener" aria-label="<?= esc($image['caption'] ?: lang('Galleries.Public.original'), 'attr') ?>">
                <img class="img-fluid rounded" src="<?= esc(site_url(ltrim($image['media']['thumbnail_url'], '/')), 'attr') ?>" alt="<?= esc($image['media']['alt_text'] ?: $image['caption'], 'attr') ?>" style="width:100%;height:220px;object-fit:contain" loading="lazy">
            </a><?php else: ?><span class="d-block border rounded p-4"><?= esc(lang('Galleries.Public.missing')) ?></span><?php endif ?>
            <?php if ($image['caption'] !== ''): ?><span class="d-block mt-2"><?= esc($image['caption']) ?></span><?php endif ?>
        </span><?php endforeach ?>
    </span>
</span>
