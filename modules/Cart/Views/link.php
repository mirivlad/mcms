<a class="<?= esc($class ?? 'mcms-cart-link', 'attr') ?>" href="<?= site_url('cart') ?>">
    <span><?= esc(lang('Cart.title')) ?></span>
    <span class="mcms-cart-count" data-cart-count><?= (int) ($count ?? 0) ?></span>
</a>
