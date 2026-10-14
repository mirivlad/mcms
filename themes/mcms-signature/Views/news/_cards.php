<?php $headingTag = ($homeCards ?? false) ? 'h3' : 'h2'; ?>
<div class="sig-story-grid">
<?php foreach ($articles as $index => $article): ?>
    <?php $previewUrl = $article['featured_image_url'] === null ? null : service('mcmsModuleServices')->get('media.library')->thumbnailUrl((int) $article['featured_media_id']); ?>
    <article class="sig-story-card">
    <?php if ($article['featured_image_url'] !== null): ?><a class="sig-story-image" href="<?= esc(site_url(ltrim($article['url'], '/')), 'attr') ?>" tabindex="-1" aria-hidden="true"><img src="<?= esc(site_url(ltrim($previewUrl, '/')), 'attr') ?>" alt="" loading="lazy"></a><?php else: ?><div class="sig-story-art" aria-hidden="true"><span><?= sprintf('%02d', $index + 1) ?></span></div><?php endif ?>
    <div class="sig-story-body"><time class="sig-eyebrow" datetime="<?= esc($article['published_at'], 'attr') ?>"><?= esc(date('d.m.Y', strtotime($article['published_at']))) ?></time><?= view('MCMS\\Modules\\News\\Views\\taxonomy', ['article' => $article]) ?><<?= $headingTag ?>><a href="<?= esc(site_url(ltrim($article['url'], '/')), 'attr') ?>"><?= esc($article['title']) ?></a></<?= $headingTag ?>><?php if (! empty($article['excerpt'])): ?><p><?= esc($article['excerpt']) ?></p><?php endif ?><span class="sig-story-arrow" aria-hidden="true">↗</span></div>
<?= view('themes/content_actions', ['targetType' => 'news.article', 'targetId' => (string) $article['id'], 'showManage' => false]) ?>
</article><?php endforeach ?>
</div>
