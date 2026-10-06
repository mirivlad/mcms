<?= view('MCMS\\Modules\\Cart\\Views\\public_assets') ?>
<section class="mcms-cart-page" data-cart-page>
    <header class="mcms-cart-heading">
        <div>
            <p class="mcms-cart-eyebrow"><?= esc($siteName) ?></p>
            <h1><?= esc(lang('Cart.title')) ?></h1>
        </div>
        <a class="btn btn-outline-secondary" href="<?= site_url('shop') ?>"><?= esc(lang('Cart.continueShopping')) ?></a>
    </header>

    <?php if (session()->getFlashdata('message')): ?>
        <div class="alert alert-success" role="status"><?= esc(session()->getFlashdata('message')) ?></div>
    <?php endif ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger" role="alert"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif ?>

    <?php if ($cart['items'] === []): ?>
        <div class="mcms-cart-empty">
            <p><?= esc(lang('Cart.empty')) ?></p>
            <a class="btn btn-primary" href="<?= site_url('shop') ?>"><?= esc(lang('Cart.continueShopping')) ?></a>
        </div>
    <?php else: ?>
        <div class="alert alert-warning mcms-cart-reservation-notice" role="note">
            <?= esc(lang('Cart.reservationNotice')) ?>
        </div>

        <div class="mcms-cart-table-wrap">
            <table class="mcms-cart-table">
                <thead>
                    <tr>
                        <th><?= esc(lang('Cart.product')) ?></th>
                        <th><?= esc(lang('Cart.price')) ?></th>
                        <th><?= esc(lang('Cart.quantity')) ?></th>
                        <th><?= esc(lang('Cart.subtotal')) ?></th>
                        <th><span class="visually-hidden"><?= esc(lang('Cart.remove')) ?></span></th>
                    </tr>
                </thead>
                <tbody data-cart-items>
                <?php foreach ($cart['items'] as $item): ?>
                    <tr data-cart-row="<?= (int) $item['variant_id'] ?>">
                        <td data-label="<?= esc(lang('Cart.product'), 'attr') ?>">
                            <a class="mcms-cart-product" href="<?= esc(site_url(ltrim($item['product_url'], '/')), 'attr') ?>">
                                <strong><?= esc($item['product_title']) ?></strong>
                                <?php if ($item['label'] !== ''): ?><span><?= esc($item['label']) ?></span><?php endif ?>
                                <small><?= esc(lang('Cart.sku')) ?>: <?= esc($item['sku']) ?></small>
                            </a>
                            <div class="mcms-cart-stock-message" data-cart-stock-message role="status">
                                <?php if (! empty($item['stock_adjusted'])): ?>
                                    <?= esc(lang('Cart.adjustedInline', [$item['requested_quantity'], $item['quantity']])) ?>
                                <?php endif ?>
                            </div>
                        </td>
                        <td data-label="<?= esc(lang('Cart.price'), 'attr') ?>"><?= esc($item['unit_price_display']) ?></td>
                        <td data-label="<?= esc(lang('Cart.quantity'), 'attr') ?>">
                            <form class="mcms-cart-quantity" method="post" action="<?= site_url('cart/update') ?>" data-cart-update>
                                <?= csrf_field() ?>
                                <input type="hidden" name="variant_id" value="<?= (int) $item['variant_id'] ?>">
                                <input type="hidden" name="return" value="/cart">
                                <button type="button" data-cart-step="-1" aria-label="<?= esc(lang('Cart.minus'), 'attr') ?>">−</button>
                                <input
                                    type="number"
                                    name="quantity"
                                    min="0"
                                    max="2000000000"
                                    step="1"
                                    inputmode="numeric"
                                    value="<?= (int) $item['quantity'] ?>"
                                    data-saved-quantity="<?= (int) $item['quantity'] ?>"
                                    aria-label="<?= esc(lang('Cart.quantity') . ': ' . $item['product_title'], 'attr') ?>"
                                >
                                <button type="button" data-cart-step="1" aria-label="<?= esc(lang('Cart.plus'), 'attr') ?>">+</button>
                            </form>
                        </td>
                        <td data-label="<?= esc(lang('Cart.subtotal'), 'attr') ?>" data-cart-item-subtotal><?= esc($item['subtotal_display']) ?></td>
                        <td class="mcms-cart-remove-cell">
                            <form method="post" action="<?= site_url('cart/remove/' . $item['variant_id']) ?>" data-cart-remove>
                                <?= csrf_field() ?>
                                <input type="hidden" name="return" value="/cart">
                                <button class="btn btn-sm btn-outline-danger" type="submit"><?= esc(lang('Cart.remove')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3"><?= esc(lang('Cart.total')) ?></th>
                        <th data-cart-subtotal><?= esc($cart['subtotal_display']) ?></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="mcms-cart-actions">
            <a class="btn btn-outline-secondary" href="<?= site_url('shop') ?>"><?= esc(lang('Cart.continueShopping')) ?></a>
            <form method="post" action="<?= site_url('cart/clear') ?>" data-cart-clear>
                <?= csrf_field() ?>
                <input type="hidden" name="return" value="/cart">
                <button class="btn btn-outline-danger" type="submit"><?= esc(lang('Cart.clear')) ?></button>
            </form>
        </div>

        <?php if ($checkoutEnabled ?? false): ?>
            <section class="mcms-cart-checkout">
                <h2><?= esc(lang('Orders.checkoutTitle')) ?></h2>
                <p class="mcms-cart-checkout-note"><?= esc(lang('Orders.reserveAtCheckout')) ?></p>
                <form method="post" action="<?= site_url('order/checkout') ?>">
                    <?= csrf_field() ?>
                    <div class="mcms-cart-checkout-grid">
                        <label>
                            <span><?= esc(lang('Orders.name')) ?></span>
                            <input class="form-control" name="name" required maxlength="190" autocomplete="name" value="<?= esc((string) old('name'), 'attr') ?>">
                        </label>
                        <label>
                            <span><?= esc(lang('Orders.phone')) ?></span>
                            <input class="form-control" name="phone" required maxlength="64" autocomplete="tel" value="<?= esc((string) old('phone'), 'attr') ?>">
                        </label>
                        <label>
                            <span><?= esc(lang('Orders.email')) ?> <small>(<?= esc(lang('Orders.optional')) ?>)</small></span>
                            <input class="form-control" type="email" name="email" maxlength="254" autocomplete="email" value="<?= esc((string) old('email'), 'attr') ?>">
                        </label>
                        <label class="mcms-cart-checkout-comment">
                            <span><?= esc(lang('Orders.comment')) ?> <small>(<?= esc(lang('Orders.optional')) ?>)</small></span>
                            <textarea class="form-control" name="comment" rows="3" maxlength="5000"><?= esc((string) old('comment')) ?></textarea>
                        </label>
                    </div>
                    <button class="btn btn-primary mt-3" type="submit"><?= esc(lang('Orders.checkout')) ?></button>
                </form>
            </section>
        <?php endif ?>
    <?php endif ?>
</section>
