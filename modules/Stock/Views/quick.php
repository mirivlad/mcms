<?php
$stock = $stock ?? ['on_hand' => 0, 'reserved' => 0, 'available' => 0];
$admin = (bool) ($admin ?? false);
$action = $admin ? 'admin/stock/variant/' . $variant['id'] : 'stock/variant/' . $variant['id'];
?>
<form
    class="<?= $admin ? 'd-flex align-items-center gap-2 flex-wrap' : 'mcms-stock-quick' ?>"
    method="post"
    action="<?= site_url($action) ?>"
    <?= $admin ? '' : 'data-stock-quick data-stock-variant="' . (int) $variant['id'] . '"' ?>
>
    <?= csrf_field() ?>
    <input type="hidden" name="return" value="<?= esc($returnPath, 'attr') ?>">
    <label class="<?= $admin ? 'd-flex flex-column small mb-0' : '' ?>">
        <span><?= esc($variant['label'] !== '' ? $variant['label'] : $variant['sku']) ?></span>
        <small><?= esc($variant['sku']) ?></small>
    </label>
    <input
        class="<?= $admin ? 'form-control form-control-sm' : '' ?>"
        <?= $admin ? 'style="width:7rem"' : '' ?>
        type="number"
        min="<?= $stock['reserved'] ?>"
        max="2000000000"
        step="1"
        name="quantity"
        value="<?= $stock['on_hand'] ?>"
        aria-label="<?= esc(lang('Stock.onHand'), 'attr') ?>"
        <?= $admin ? '' : 'data-stock-quantity' ?>
    >
    <button class="<?= $admin ? 'btn btn-sm btn-primary' : '' ?>" type="submit" <?= $admin ? '' : 'data-stock-submit' ?>><?= esc(lang('Stock.ok')) ?></button>
    <span
        class="<?= $admin ? 'badge bg-azure-lt' : 'mcms-stock-meta' ?>"
        <?= $admin ? '' : 'data-stock-meta data-available-label="' . esc(lang('Stock.availableShort'), 'attr') . '" data-reserved-label="' . esc(lang('Stock.reservedShort'), 'attr') . '"' ?>
    ><?= esc(lang('Stock.availableShort')) ?> <?= $stock['available'] ?><?php if ($stock['reserved'] > 0): ?> · <?= esc(lang('Stock.reservedShort')) ?> <?= $stock['reserved'] ?><?php endif ?></span>
    <?php if (! $admin): ?><span class="mcms-stock-quick-message" data-stock-message role="status"></span><?php endif ?>
</form>
