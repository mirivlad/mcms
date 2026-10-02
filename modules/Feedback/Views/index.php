<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<h2 class="page-title mb-3"><?= esc(lang('Feedback.Admin.title')) ?></h2>
<div class="card"><div class="table-responsive"><table class="table card-table table-vcenter">
    <thead><tr><th><?= esc(lang('Feedback.Public.name')) ?></th><th><?= esc(lang('Feedback.Public.email')) ?></th><th><?= esc(lang('Feedback.Admin.received')) ?></th><th><?= esc(lang('MCMS.common.status')) ?></th></tr></thead>
    <tbody><?php if ($messages === []): ?><tr><td colspan="4"><?= esc(lang('Feedback.Admin.empty')) ?></td></tr><?php endif ?>
    <?php foreach ($messages as $message): ?><tr><td><a href="<?= site_url('admin/feedback/' . $message['id']) ?>"><?= esc($message['name']) ?></a></td><td><?= esc($message['email']) ?></td><td><?= esc($message['created_at']) ?></td><td><?= esc(lang($message['read_at'] === null ? 'Feedback.Admin.unread' : 'Feedback.Admin.read')) ?></td></tr><?php endforeach ?>
    </tbody>
</table></div></div>
<?php if ($lastPage > 1): ?><nav class="d-flex justify-content-between mt-3" aria-label="<?= esc(lang('Feedback.Admin.title'), 'attr') ?>">
    <div><?php if ($page > 1): ?><a href="<?= site_url('admin/feedback') . '?page=' . ($page - 1) ?>"><?= esc(lang('MCMS.common.previousPage')) ?></a><?php endif ?></div><span><?= (int) $page ?> / <?= (int) $lastPage ?></span><div><?php if ($page < $lastPage): ?><a href="<?= site_url('admin/feedback') . '?page=' . ($page + 1) ?>"><?= esc(lang('MCMS.common.nextPage')) ?></a><?php endif ?></div>
</nav><?php endif ?>
<?= $this->endSection() ?>
