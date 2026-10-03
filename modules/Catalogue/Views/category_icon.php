<?php if ($category['icon_type'] === 'image' && $category['image_url'] !== null): ?>
<img class="mcms-category-icon" src="<?= esc(site_url(ltrim($category['image_url'], '/')), 'attr') ?>" alt="" loading="lazy">
<?php else: ?>
<i class="ti ti-<?= esc($category['icon'], 'attr') ?> mcms-category-icon" style="color:<?= esc($category['icon_color'], 'attr') ?>" aria-hidden="true"></i>
<?php endif ?>
