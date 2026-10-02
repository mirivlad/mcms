<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3"><h2 class="page-title"><?= esc(lang('Feedback.Admin.title')) ?></h2><a href="<?= site_url('admin/feedback') ?>" class="btn btn-link"><?= esc(lang('MCMS.common.back')) ?></a></div>
<div class="card"><div class="card-body">
    <p><strong><?= esc($message['name']) ?></strong> &lt;<?= esc($message['email']) ?>&gt;</p><p class="text-secondary"><?= esc($message['created_at']) ?></p>
    <div class="text-break mb-4"><?= nl2br(esc($message['message'])) ?></div>
    <div class="btn-list">
        <?php if ($canManage && $message['read_at'] === null): ?><form method="post" action="<?= site_url('admin/feedback/' . $message['id'] . '/read') ?>"><?= csrf_field() ?><button class="btn btn-primary"><?= esc(lang('Feedback.Admin.markRead')) ?></button></form><?php endif ?>
        <?php if ($canDelete): ?><form method="post" action="<?= site_url('admin/feedback/' . $message['id'] . '/delete') ?>" onsubmit="return confirm(<?= esc(json_encode(lang('Feedback.Admin.deleteConfirm')), 'attr') ?>);">
            <?= csrf_field() ?><button class="btn btn-outline-danger"><?= esc(lang('MCMS.common.delete')) ?></button>
        </form><?php endif ?>
    </div>
</div></div>
<?= $this->endSection() ?>
