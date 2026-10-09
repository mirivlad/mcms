<!doctype html>
<html lang="<?= esc(service('request')->getLocale(), 'attr') ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= esc(lang('MCMS.modules.maintenance')) ?></title></head>
<body><h1><?= esc(lang('MCMS.modules.maintenance')) ?></h1><p><?= esc(lang('MCMS.modules.maintenanceHint')) ?></p><p><a href="<?= site_url('admin/modules') ?>"><?= esc(lang('MCMS.modules.openRecovery')) ?></a></p></body>
</html>
