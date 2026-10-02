<?php if (! empty($article['category']) || ! empty($article['tag_names'])): ?>
<div class="d-flex flex-wrap gap-2 my-2">
    <?php if (! empty($article['category'])): ?><a class="badge text-bg-light border text-decoration-none" style="max-width:100%;white-space:normal;overflow-wrap:anywhere" href="<?= esc(site_url('news') . '?' . http_build_query(['category' => $article['category_id']]), 'attr') ?>"><?= esc($article['category']['name']) ?></a><?php endif ?>
    <?php foreach ($article['tag_names'] ?? [] as $name): ?><a class="badge text-bg-light border text-decoration-none" style="max-width:100%;white-space:normal;overflow-wrap:anywhere" href="<?= esc(site_url('news') . '?' . http_build_query(['tag' => $name]), 'attr') ?>">#<?= esc($name) ?></a><?php endforeach ?>
</div>
<?php endif ?>
