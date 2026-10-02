<span class="sig-gallery-embed" role="group" aria-label="<?= esc($album['title'], 'attr') ?>" data-gallery data-gallery-title="<?= esc($album['title'], 'attr') ?>" data-gallery-id="<?= (int) $album['id'] ?>">
    <span class="sig-embed-heading"><a href="<?= esc(site_url(ltrim($album['url'], '/')), 'attr') ?>"><?= esc($album['title']) ?></a><span class="sig-eyebrow"><?= esc(lang('Signature.imageCount')) ?> · <?= count($album['images']) ?></span></span>
    <?= view('MCMS\\Themes\\Signature\\Views\\galleries\\_images', ['album' => $album]) ?>
</span>
