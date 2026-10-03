<span class="site-gallery-embed" role="group" aria-label="<?= esc($album['title'], 'attr') ?>" data-gallery data-gallery-title="<?= esc($album['title'], 'attr') ?>" data-gallery-id="<?= (int) $album['id'] ?>">
    <span class="site-embed-heading"><a href="<?= esc(site_url(ltrim($album['url'], '/')), 'attr') ?>"><?= esc($album['title']) ?></a><span class="site-eyebrow"><?= esc(lang('Depth.imageCount')) ?> · <?= count($album['images']) ?></span></span>
    <?= view('MCMS\\Themes\\Depth\\Views\\galleries\\_images', ['album' => $album]) ?>
</span>
