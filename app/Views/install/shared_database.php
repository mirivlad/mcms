<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>mCMS — shared hosting setup</title>
<link rel="stylesheet" href="<?= site_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
</head>
<body class="bg-body-tertiary">
<main class="container py-5" style="max-width:720px">
<section class="card shadow-sm"><div class="card-body p-4">
<h1 class="h3">mCMS — database setup</h1>
<p class="text-secondary">Step 1 of 2. Create an empty MySQL/MariaDB database in your hosting panel.
mCMS will initialize its tables and then let you create the administrator account.</p>
<?php if ($errors !== []): ?>
<div class="alert alert-danger" role="alert">
<h2 class="h6">Hosting requirements / setup error</h2>
<ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= esc($error) ?></li><?php endforeach ?></ul>
</div>
<?php endif ?>
<form action="<?= site_url('install') ?>" method="post">
<?= csrf_field() ?>
<div class="row g-3">
<div class="col-md-8"><label class="form-label" for="db_host">Database host</label>
<input class="form-control" name="db_host" id="db_host" maxlength="255" required value="<?= esc((string) old('db_host', 'localhost')) ?>"></div>
<div class="col-md-4"><label class="form-label" for="db_port">Port</label>
<input class="form-control" type="number" name="db_port" id="db_port" min="1" max="65535" required value="3306"></div>
<div class="col-md-6"><label class="form-label" for="db_name">Database name</label>
<input class="form-control" name="db_name" id="db_name" maxlength="255" required></div>
<div class="col-md-6"><label class="form-label" for="db_user">Database user</label>
<input class="form-control" name="db_user" id="db_user" maxlength="255" required></div>
<div class="col-12"><label class="form-label" for="db_password">Database password</label>
<input class="form-control" type="password" name="db_password" id="db_password" maxlength="255" autocomplete="new-password" required></div>
</div>
<p class="small text-secondary mt-3">Configuration is saved only outside the public web directory.
Use HTTPS; do not use an existing database with unrelated data.</p>
<button class="btn btn-primary" type="submit" <?= $errors !== [] ? 'disabled' : '' ?>>Connect and initialize database</button>
</form>
</div></section>
</main></body></html>
