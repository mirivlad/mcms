<?php $categoryAssets = dirname(__DIR__) . '/assets/'; ?>
<link rel="stylesheet" href="<?= site_url('shop/assets/categories.css') ?>?v=<?= substr(hash_file('sha256', $categoryAssets . 'categories.css'), 0, 12) ?>">
<script src="<?= site_url('shop/assets/categories.js') ?>?v=<?= substr(hash_file('sha256', $categoryAssets . 'categories.js'), 0, 12) ?>" defer></script>
