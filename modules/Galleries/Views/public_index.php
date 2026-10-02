<!doctype html>
<html lang="<?= esc(service('request')->getLocale(), 'attr') ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= esc($metaTitle) ?></title><meta name="description" content="<?= esc($metaDescription ?? '', 'attr') ?>"><link rel="stylesheet" href="<?= site_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>"></head><body><main class="container py-5">
<?= view('MCMS\\Modules\\Galleries\\Views\\_index') ?>
</main></body></html>
