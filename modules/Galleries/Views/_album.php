<a href="<?= site_url('galleries') ?>"><?= esc(lang('Galleries.Public.back')) ?></a>
<h1 class="mt-3 mb-3"><?= esc($album['title']) ?></h1>
<p class="mb-4"><?= nl2br(esc($album['description'])) ?></p>
<div class="row g-4">
    <?php foreach ($album['images'] as $image): ?><figure class="col-sm-6 col-lg-4">
        <?php if ($image['media'] !== null): ?>
            <a href="<?= esc(site_url(ltrim($image['media']['url'], '/')), 'attr') ?>" target="_blank" rel="noopener" aria-label="<?= esc(lang('Galleries.Public.original'), 'attr') ?>">
                <img class="img-fluid rounded w-100" src="<?= esc(site_url(ltrim($image['media']['thumbnail_url'], '/')), 'attr') ?>" alt="<?= esc($image['media']['alt_text'] ?? $image['caption'], 'attr') ?>" style="height:240px;object-fit:contain" loading="lazy">
            </a>
        <?php else: ?><div class="border rounded p-4 text-secondary"><?= esc(lang('Galleries.Public.missing')) ?></div><?php endif ?>
        <?php if ($image['caption'] !== ''): ?><figcaption class="mt-2"><?= esc($image['caption']) ?></figcaption><?php endif ?>
    </figure><?php endforeach ?>
</div>
