<a class="<?= esc($class ?? 'mcms-cart-link', 'attr') ?>" href="<?= site_url('cart') ?>" data-cart-modal-link data-cart-fragment="<?= esc(site_url('cart/fragment'), 'attr') ?>">
    <span><?= esc(lang('Cart.title')) ?></span>
    <span class="mcms-cart-count" data-cart-count><?= (int) ($count ?? 0) ?></span>
</a>
