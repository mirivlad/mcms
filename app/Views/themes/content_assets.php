<?php
$cssPath = ROOTPATH . 'public/assets/css/content-extras.css';
?>
<link rel="stylesheet" href="<?= esc(site_url('assets/css/content-extras.css') . '?v=' . substr(hash_file('sha256', $cssPath), 0, 12), 'attr') ?>">
