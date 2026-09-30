<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">Users</div>
            <h2 class="page-title">Create user</h2>
        </div>
    </div>
</div>

<?php $errors = session()->getFlashdata('errors') ?? []; ?>
<?php if ($errors !== []): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= esc($error) ?></li>
            <?php endforeach ?>
        </ul>
    </div>
<?php endif ?>

<div class="card">
    <div class="card-body">
        <form method="post">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" id="email" type="email" name="email" required value="<?= esc(old('email')) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <input class="form-control" id="username" name="username" minlength="3" maxlength="30" required value="<?= esc(old('username')) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Initial password</label>
                <input class="form-control" id="password" type="password" name="password" minlength="8" required>
            </div>
            <button class="btn btn-primary" type="submit">Create user</button>
            <a class="btn btn-link" href="<?= site_url('admin/users') ?>">Cancel</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
