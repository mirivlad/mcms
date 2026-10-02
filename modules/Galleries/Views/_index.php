<h1 class="mb-4"><?= esc(lang('Galleries.Public.title')) ?></h1>
<?php if ($albums === []): ?><p><?= esc(lang('Galleries.Public.empty')) ?></p><?php endif ?>
<div class="row g-4">
    <?php foreach ($albums as $album): ?><div class="col-sm-6 col-lg-4"><a class="card h-100 text-decoration-none" href="<?= esc(site_url(ltrim($album['url'], '/')), 'attr') ?>">
        <?php if ($album['cover'] !== null): ?><img class="card-img-top" src="<?= esc(site_url(ltrim($album['cover']['thumbnail_url'], '/')), 'attr') ?>" alt="<?= esc($album['cover']['alt_text'] ?? '', 'attr') ?>" style="height:220px;object-fit:contain" loading="lazy"><?php endif ?>
        <div class="card-body"><h2 class="h5"><?= esc($album['title']) ?></h2><p class="card-text"><?= esc(mb_substr($album['description'], 0, 200)) ?></p></div>
    </a></div><?php endforeach ?>
</div>
<?php if ($lastPage > 1): ?><nav class="d-flex justify-content-between align-items-center mt-4" aria-label="<?= esc(lang('Galleries.Public.title'), 'attr') ?>">
    <div><?php if ($page > 1): ?><a href="<?= esc(site_url('galleries') . '?page=' . ($page - 1), 'attr') ?>"><?= esc(lang('MCMS.common.previousPage')) ?></a><?php endif ?></div>
    <span><?= (int) $page ?> / <?= (int) $lastPage ?></span>
    <div><?php if ($page < $lastPage): ?><a href="<?= esc(site_url('galleries') . '?page=' . ($page + 1), 'attr') ?>"><?= esc(lang('MCMS.common.nextPage')) ?></a><?php endif ?></div>
</nav><?php endif ?>
