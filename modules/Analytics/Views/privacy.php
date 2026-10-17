<!doctype html>
<html lang="<?= esc(service('request')->getLocale(), 'attr') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= esc(lang('Analytics.privacyTitle')) ?></title>
<style>
body{font-family:system-ui,sans-serif;background:#f5f6f8;color:#20242a;margin:0;padding:2rem}
main{max-width:45rem;margin:3rem auto;background:#fff;padding:2rem;border-radius:1rem;box-shadow:0 5px 25px #0001}
button{border:1px solid #586e8a;border-radius:.5rem;padding:.7rem 1rem;background:#fff;cursor:pointer}
button.primary{background:#244b79;color:white}
p{line-height:1.55}form{display:inline-block;margin:.3rem .6rem .3rem 0}
</style>
</head>
<body>
<main>
<h1><?= esc(lang('Analytics.privacyTitle')) ?></h1>
<p><?= esc(lang('Analytics.privacyDescription')) ?></p>
<p><?= esc(lang('Analytics.privacyStatus')) ?>:
<strong><?= esc(lang('Analytics.' . ($choice === 'yes' ? 'privacyAllowed' : ($choice === 'no' ? 'privacyDenied' : 'privacyUnset')))) ?></strong></p>
<form method="post" action="<?= esc(site_url('analytics/privacy'), 'attr') ?>">
<?= csrf_field() ?>
<input type="hidden" name="choice" value="yes">
<button class="primary" type="submit"><?= esc(lang('Analytics.privacyAccept')) ?></button>
</form>
<form method="post" action="<?= esc(site_url('analytics/privacy'), 'attr') ?>">
<?= csrf_field() ?>
<input type="hidden" name="choice" value="no">
<button type="submit"><?= esc(lang('Analytics.privacyDecline')) ?></button>
</form>
<p><a href="<?= esc(site_url('/'), 'attr') ?>"><?= esc(lang('Analytics.privacyBack')) ?></a></p>
</main>
</body></html>
