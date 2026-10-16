<?php $assetDirectory = dirname(__DIR__) . '/assets/'; ?>
<link rel="stylesheet" href="<?= esc(site_url('cart/assets/cart.css') . '?v=' . substr(hash_file('sha256', $assetDirectory . 'cart.css'), 0, 12), 'attr') ?>">
<script src="<?= esc(site_url('cart/assets/cart.js') . '?v=' . substr(hash_file('sha256', $assetDirectory . 'cart.js'), 0, 12), 'attr') ?>" defer></script>
