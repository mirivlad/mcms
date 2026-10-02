<?php $contacts = $themeAppearance ?? []; ?>
<?php if (! empty($contacts['phone']) || ! empty($contacts['email']) || ! empty($contacts['address'])): ?>
<address class="mcms-contacts" aria-label="<?= esc(lang('MCMS.themes.contacts'), 'attr') ?>">
    <?php if (! empty($contacts['phone'])): ?>
    <?php $phone = preg_replace('/[^0-9+]/', '', $contacts['phone']); ?>
    <?php if ($phone !== ''): ?><a href="<?= esc('tel:' . $phone, 'attr') ?>"><?= esc($contacts['phone']) ?></a><?php else: ?><span><?= esc($contacts['phone']) ?></span><?php endif ?>
    <?php endif ?>
    <?php if (! empty($contacts['email'])): ?><a href="<?= esc('mailto:' . $contacts['email'], 'attr') ?>"><?= esc($contacts['email']) ?></a><?php endif ?>
    <?php if (! empty($contacts['address'])): ?><span><?= nl2br(esc($contacts['address'])) ?></span><?php endif ?>
</address>
<?php endif ?>
