<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="page-header mb-3"><h2 class="page-title"><?= esc(lang('Comments.Admin.title')) ?></h2></div>
<nav class="mb-3 d-flex gap-2 flex-wrap">
    <?php foreach (['pending','published','rejected','spam','all'] as $choice): ?>
        <a class="btn btn-sm <?= $status === $choice ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= site_url('admin/comments') . '?status=' . $choice ?>">
            <?= esc($choice === 'all' ? 'Все / All' : lang('Comments.Status.' . $choice)) ?>
        </a>
    <?php endforeach ?>
</nav>
<?php if ($comments === []): ?><div class="alert alert-info"><?= esc(lang('Comments.Admin.empty')) ?></div><?php endif ?>
<?php foreach ($comments as $comment): ?>
    <article class="card mb-3">
        <div class="card-header d-flex justify-content-between gap-3 flex-wrap">
            <div>
                <strong><?= esc($comment['author_name']) ?></strong>
                <span class="text-secondary ms-2"><?= esc($comment['created_at']) ?></span>
                <span class="badge ms-2"><?= esc(lang('Comments.Status.' . $comment['status'])) ?></span>
            </div>
            <div>
                <?php if ($targets[$comment['id']] !== null): ?>
                    <a href="<?= esc(site_url(ltrim($targets[$comment['id']]['url'], '/')), 'attr') ?>" target="_blank" rel="noopener noreferrer"><?= esc($targets[$comment['id']]['label']) ?></a>
                <?php else: ?><span class="text-secondary"><?= esc($comment['target_type'] . ':' . $comment['target_id']) ?></span><?php endif ?>
            </div>
        </div>
        <div class="card-body">
            <p style="white-space:pre-wrap;overflow-wrap:anywhere"><?= esc($comment['body']) ?></p>
            <?php if (($reports[$comment['id']] ?? []) !== []): ?>
                <div class="alert alert-warning">
                    <strong><?= esc(lang('Comments.Admin.reports')) ?>: <?= count($reports[$comment['id']]) ?></strong>
                    <?php foreach ($reports[$comment['id']] as $report): ?><div><?= esc($report['reason']) ?></div><?php endforeach ?>
                </div>
            <?php endif ?>
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($canModerate && $comment['status'] !== 'deleted'): ?>
                    <?php foreach (['published','rejected','spam','pending'] as $next): ?>
                    <form method="post" action="<?= site_url('admin/comments/' . $comment['id'] . '/status') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="status" value="<?= esc($next, 'attr') ?>">
                        <button class="btn btn-sm <?= $next === 'published' ? 'btn-success' : 'btn-outline-secondary' ?>" type="submit"><?= esc(lang('Comments.Status.' . $next)) ?></button>
                    </form>
                    <?php endforeach ?>
                <?php endif ?>
                <?php if ($canDelete && $comment['status'] !== 'deleted'): ?>
                    <form method="post" action="<?= site_url('admin/comments/' . $comment['id'] . '/delete') ?>" onsubmit="return confirm('Delete this comment?');">
                        <?= csrf_field() ?><button type="submit" class="btn btn-sm btn-outline-danger"><?= esc(lang('Comments.Admin.delete')) ?></button>
                    </form>
                <?php endif ?>
            </div>
        </div>
    </article>
<?php endforeach ?>
<?php if ($lastPage > 1): ?>
<nav class="d-flex justify-content-between mt-3"><div><?php if ($page > 1): ?><a href="<?= site_url('admin/comments') . '?status=' . $status . '&page=' . ($page - 1) ?>"><?= esc(lang('MCMS.common.previousPage')) ?></a><?php endif ?></div><span><?= (int) $page ?> / <?= (int) $lastPage ?></span><div><?php if ($page < $lastPage): ?><a href="<?= site_url('admin/comments') . '?status=' . $status . '&page=' . ($page + 1) ?>"><?= esc(lang('MCMS.common.nextPage')) ?></a><?php endif ?></div></nav>
<?php endif ?>
<?= $this->endSection() ?>
