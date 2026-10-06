<?php
$variants = $variants ?? [];
$levels = $stockLevels ?? [];
$showVariantSelect = (bool) ($showVariantSelect ?? false);
$first = $variants[0] ?? null;
$firstAvailable = $first === null ? 0 : (int) (($levels[$first['id']]['available'] ?? 0));
?>
<?php if ($first !== null): ?>
<form class="mcms-cart-add-form" method="post" action="<?= site_url('cart/add') ?>" data-cart-add data-available="<?= $firstAvailable ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="return" value="<?= esc($returnPath ?? '/cart', 'attr') ?>">
    <input type="hidden" name="variant_id" value="<?= (int) $first['id'] ?>" data-cart-variant>
    <?php if ($showVariantSelect && count($variants) > 1): ?>
        <label>
            <span><?= esc(lang('Catalogue.chooseVariant')) ?></span>
            <select class="form-select form-select-sm" data-cart-variant-select>
                <?php foreach ($variants as $variant): $available = (int) (($levels[$variant['id']]['available'] ?? 0)); ?>
                    <option value="<?= (int) $variant['id'] ?>" data-available="<?= $available ?>">
                        <?= esc($variant['label'] !== '' ? $variant['label'] : $variant['sku']) ?>
                    </option>
                <?php endforeach ?>
            </select>
        </label>
    <?php endif ?>
    <label>
        <span><?= esc(lang('Cart.quantity')) ?></span>
        <input class="form-control form-control-sm" type="number" name="quantity" value="1" min="1" max="2000000000" step="1" inputmode="numeric">
    </label>
    <button class="btn btn-primary btn-sm" type="submit" data-cart-submit><?= esc(lang('Cart.add')) ?></button>
    <span class="mcms-cart-availability" data-cart-available data-label="<?= esc(lang('Cart.available'), 'attr') ?>" data-empty="<?= esc(lang('Cart.outOfStock'), 'attr') ?>"></span>
    <span class="mcms-cart-add-message" data-cart-message role="status"></span>
</form>
<?php endif ?>