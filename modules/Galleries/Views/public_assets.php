<?php if (service('mcmsModuleServices')->has('galleries.shortcode')): ?>
<link rel="stylesheet" href="<?= esc(site_url('galleries/assets/display.css?v=' . substr(hash_file('sha256', dirname(__DIR__) . '/assets/display.css'), 0, 12)), 'attr') ?>">
<script src="<?= esc(site_url('galleries/assets/display.js?v=' . substr(hash_file('sha256', dirname(__DIR__) . '/assets/display.js'), 0, 12)), 'attr') ?>" defer></script>
<?php endif ?>
