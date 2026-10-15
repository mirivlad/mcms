<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php use MCMS\Modules\Notifications\Services\Notifications; ?>
<h2 class="page-title mb-3"><?= esc(lang('Notifications.title')) ?></h2>
<div class="row g-3">
    <div class="col-lg-7"><div class="card"><div class="card-body">
        <form method="post" action="<?= $settingsUrl('admin/notifications') ?>">
            <?= csrf_field() ?>
            <label class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="enabled" value="1" <?= old('enabled', $preferences['enabled']) ? 'checked' : '' ?>><span class="form-check-label"><?= esc(lang('Notifications.enabled')) ?></span></label>
            <div class="mb-3"><label class="form-label" for="recipient"><?= esc(lang('Notifications.recipient')) ?></label><input class="form-control" id="recipient" name="recipient" type="email" maxlength="254" value="<?= esc((string) old('recipient', $preferences['recipient']), 'attr') ?>"><div class="form-hint"><?= esc(lang('Notifications.recipientHint')) ?></div></div>
            <fieldset class="mb-3"><legend class="form-label"><?= esc(lang('Notifications.events')) ?></legend>
                <?php foreach (Notifications::EVENTS as $event): ?><label class="form-check"><input class="form-check-input" name="events[]" type="checkbox" value="<?= $event ?>" <?= in_array($event, (array) old('events', $preferences['events']), true) ? 'checked' : '' ?>><span class="form-check-label"><?= esc(lang('Orders.status.' . $event)) ?></span></label><?php endforeach ?>
            </fieldset>
            <div class="mb-3"><label class="form-label" for="subject"><?= esc(lang('Notifications.subject')) ?></label><input class="form-control" id="subject" name="subject" required maxlength="255" value="<?= esc((string) old('subject', $preferences['subject']), 'attr') ?>"></div>
            <div class="mb-3"><label class="form-label" for="body"><?= esc(lang('Notifications.body')) ?></label><textarea class="form-control" id="body" name="body" rows="9" required maxlength="10000"><?= esc((string) old('body', $preferences['body'])) ?></textarea><div class="form-hint"><?= esc(lang('Notifications.tokens')) ?> <span class="text-break"><?= esc(implode(', ', array_map(static fn (string $token): string => '{' . $token . '}', Notifications::TOKENS))) ?></span></div></div>
            <button class="btn btn-primary" type="submit"><?= esc(lang('MCMS.common.save')) ?></button>
        </form>
    </div></div></div>
    <div class="col-lg-5"><div class="card"><div class="card-body">
        <h3 class="card-title"><?= esc(lang('Notifications.transport')) ?></h3>
        <dl><dt><?= esc(lang('Notifications.sender')) ?></dt><dd class="text-break"><?= esc($transport->fromEmail) ?></dd><dt><?= esc(lang('Notifications.server')) ?></dt><dd class="text-break"><?= esc($transport->SMTPHost . ':' . $transport->SMTPPort) ?></dd></dl>
        <p class="text-secondary"><?= esc(lang('Notifications.transportHint')) ?></p>
        <form method="post" action="<?= $settingsUrl('admin/notifications/test') ?>"><?= csrf_field() ?><button class="btn btn-outline-primary" type="submit"><?= esc(lang('Notifications.test')) ?></button></form>
        <div class="form-hint mt-2"><?= esc(lang('Notifications.testHint')) ?></div>
    </div></div></div>
</div>
<div class="card mt-3"><div class="card-body">
    <h3 class="card-title"><?= esc(lang('Notifications.comments')) ?></h3>
    <form method="post" action="<?= $settingsUrl('admin/notifications/comments') ?>">
        <?= csrf_field() ?>
        <label class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="enabled" value="1" <?= $commentPreferences['enabled'] ? 'checked' : '' ?>><span class="form-check-label"><?= esc(lang('Notifications.commentEnabled')) ?></span></label>
        <label class="form-label" for="comment-recipient"><?= esc(lang('Notifications.recipient')) ?></label>
        <input class="form-control mb-3" id="comment-recipient" name="recipient" type="email" maxlength="254" value="<?= esc($commentPreferences['recipient'], 'attr') ?>">
        <fieldset class="mb-3"><legend class="form-label"><?= esc(lang('Notifications.commentEventsLabel')) ?></legend>
        <?php foreach (['pending', 'published'] as $event): ?><label class="form-check"><input class="form-check-input" type="checkbox" name="events[]" value="<?= $event ?>" <?= in_array($event, $commentPreferences['events'], true) ? 'checked' : '' ?>><span class="form-check-label"><?= esc(lang('Notifications.commentEvents.' . $event)) ?></span></label><?php endforeach ?>
        </fieldset>
        <button class="btn btn-primary" type="submit"><?= esc(lang('MCMS.common.save')) ?></button>
    </form>
</div></div>
<div class="card mt-3"><div class="card-header d-flex flex-wrap gap-2 justify-content-between">
    <h3 class="card-title"><?= esc(lang('Notifications.queue', [$pending])) ?></h3>
    <form method="post" action="<?= $settingsUrl('admin/notifications/retry') ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-primary" type="submit" <?= (! $preferences['enabled'] && ! $commentPreferences['enabled']) || $pending === 0 ? 'disabled' : '' ?>><i class="ti ti-refresh me-1" aria-hidden="true"></i><?= esc(lang('Notifications.retry')) ?></button></form>
</div><div class="table-responsive"><table class="table table-vcenter"><thead><tr><th><?= esc(lang('Notifications.order')) ?></th><th><?= esc(lang('Notifications.recipient')) ?></th><th><?= esc(lang('Notifications.state')) ?></th><th><?= esc(lang('Notifications.attempts')) ?></th><th><?= esc(lang('Notifications.date')) ?></th></tr></thead><tbody>
<?php foreach ($recent as $row): ?><tr>
    <td><?php if (isset($row['order_id'])): ?>#<?= (int) $row['order_id'] ?><div class="text-secondary small"><?= esc(lang('Orders.status.' . $row['event'])) ?></div><?php else: ?><?= esc(lang('Notifications.comments')) ?><div class="text-secondary small"><?= esc(lang('Notifications.commentEvents.' . substr($row['event'], 9))) ?></div><?php endif ?></td>
    <td><?= esc($row['recipient']) ?></td><td><?= esc(lang('Notifications.states.' . $row['status'])) ?><?php if ($row['last_error']): ?><div class="text-danger small"><?= esc($row['last_error']) ?></div><?php endif ?></td>
    <td><?= (int) $row['attempts'] ?></td><td class="text-nowrap"><?= esc($row['sent_at'] ?? $row['created_at']) ?></td>
</tr><?php endforeach ?>
<?php if ($recent === []): ?><tr><td colspan="5" class="text-secondary"><?= esc(lang('Notifications.empty')) ?></td></tr><?php endif ?>
</tbody></table></div></div>
<?= $this->endSection() ?>
