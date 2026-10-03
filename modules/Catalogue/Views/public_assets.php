<?php $assetDirectory = dirname(__DIR__) . '/assets/'; ?>
<link rel="stylesheet" href="<?= site_url('shop/assets/shop.css') ?>?v=<?= substr(hash_file('sha256', $assetDirectory . 'shop.css'), 0, 12) ?>">
<script src="<?= site_url('shop/assets/shop.js') ?>?v=<?= substr(hash_file('sha256', $assetDirectory . 'shop.js'), 0, 12) ?>" defer></script>
