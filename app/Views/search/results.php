<section class="container py-5" style="max-width:1000px">
    <h1><?= esc(lang('MCMS.search.title')) ?></h1>
    <form method="get" action="<?= site_url('search/results') ?>" class="d-flex gap-2 my-4" role="search">
        <label class="visually-hidden" for="site-search-query"><?= esc(lang('MCMS.search.query')) ?></label>
        <input id="site-search-query" class="form-control" type="search" name="q" value="<?= esc($query, 'attr') ?>" maxlength="120" placeholder="<?= esc(lang('MCMS.search.query'), 'attr') ?>">
        <button class="btn btn-primary" type="submit"><?= esc(lang('MCMS.common.search')) ?></button>
    </form>
    <?php if ($query === ''): ?><p><?= esc(lang('MCMS.search.hint')) ?></p>
    <?php elseif ($items === []): ?><p role="status"><?= esc(lang('MCMS.search.empty')) ?></p>
    <?php else: ?>
    <p role="status"><?= esc(sprintf(lang('MCMS.search.count'), $count)) ?></p>
    <div class="vstack gap-3">
    <?php foreach ($items as $item): ?>
        <article class="border rounded p-4" style="overflow-wrap:anywhere;background:var(--mcms-surface,#fffdf9)">
            <p class="small text-secondary mb-2"><?= esc($item['type']) ?></p>
            <h2 class="h4"><a href="<?= esc(site_url(ltrim($item['url'], '/')), 'attr') ?>"><?= esc($item['title']) ?></a></h2>
            <p class="mb-0"><?= esc($item['text']) ?></p>
        </article>
    <?php endforeach ?>
    </div>
    <?php endif ?>
    <?php if ($lastPage > 1): ?><nav class="d-flex justify-content-between align-items-center gap-3 mt-4" aria-label="<?= esc(lang('MCMS.search.title'), 'attr') ?>">
        <span><?php if ($page > 1): ?><a class="btn btn-outline-secondary" href="<?= esc(site_url('search/results') . '?' . http_build_query(['q' => $query, 'page' => $page - 1]), 'attr') ?>">← <?= esc(lang('MCMS.search.previous')) ?></a><?php endif ?></span>
        <span><?= (int) $page ?> / <?= (int) $lastPage ?></span>
        <span><?php if ($page < $lastPage): ?><a class="btn btn-outline-secondary" href="<?= esc(site_url('search/results') . '?' . http_build_query(['q' => $query, 'page' => $page + 1]), 'attr') ?>"><?= esc(lang('MCMS.search.next')) ?> →</a><?php endif ?></span>
    </nav><?php endif ?>
</section>
