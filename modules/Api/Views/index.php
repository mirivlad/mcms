<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="page-header mb-4">
    <h2 class="page-title"><?= esc(lang('Api.title')) ?></h2>
    <div class="text-secondary mt-1"><?= esc(lang('Api.hint')) ?></div>
</div>
<?php if (is_string($secret)): ?>
<div class="card mb-3 border-warning">
    <div class="card-body">
        <label class="form-label" for="api-secret"><?= esc(lang('Api.secretOnce')) ?></label>
        <input class="form-control font-monospace" id="api-secret" type="text" readonly value="<?= esc($secret, 'attr') ?>" autocomplete="off" spellcheck="false">
    </div>
</div>
<?php endif ?>
<div class="card mb-3">
    <div class="card-header"><h3 class="card-title"><?= esc(lang('Api.create')) ?></h3></div>
    <div class="card-body">
        <form method="post" action="<?= site_url('admin/api/keys') ?>">
            <?= csrf_field() ?>
            <div class="row g-3 mb-3">
                <div class="col-md-8"><label class="form-label" for="api-name"><?= esc(lang('Api.name')) ?></label><input class="form-control" id="api-name" name="name" maxlength="190" required value="<?= esc(old('name'), 'attr') ?>" placeholder="<?= esc(lang('Api.nameExample'), 'attr') ?>"></div>
                <div class="col-md-4"><label class="form-label" for="api-expires"><?= esc(lang('Api.expires')) ?></label><input class="form-control" id="api-expires" name="expires" type="date" min="<?= date('Y-m-d') ?>" value="<?= esc(old('expires'), 'attr') ?>"><div class="form-hint"><?= esc(lang('Api.expiryHint')) ?></div></div>
            </div>
            <fieldset class="mb-3"><legend class="form-label"><?= esc(lang('Api.scopesTitle')) ?></legend>
                <div class="row g-2">
                <?php foreach ($allowedScopes as $scope): ?>
                    <div class="col-sm-6"><label class="form-check"><input class="form-check-input" type="checkbox" name="scopes[]" value="<?= esc($scope, 'attr') ?>"><span class="form-check-label"><?= esc(lang('Api.scopes.' . str_replace('.', '_', $scope))) ?></span></label></div>
                <?php endforeach ?>
                </div>
                <div class="form-hint mt-2"><?= esc(lang('Api.ownerHint')) ?></div>
            </fieldset>
            <button type="submit" class="btn btn-primary" <?= $allowedScopes === [] ? 'disabled' : '' ?>><?= esc(lang('Api.create')) ?></button>
        </form>
    </div>
</div>
<div class="card mb-3">
    <div class="table-responsive"><table class="table table-vcenter card-table">
        <thead><tr><th><?= esc(lang('Api.name')) ?></th><th><?= esc(lang('Api.scopesTitle')) ?></th><th><?= esc(lang('Api.status')) ?></th><th><?= esc(lang('Api.lastUsed')) ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($keys as $key): ?>
            <?php $active = $key['revoked_at'] === null && ($key['expires_at'] === null || $key['expires_at'] > date('Y-m-d H:i:s')); ?>
            <tr>
                <td><div class="fw-semibold"><?= esc($key['name']) ?></div><div class="text-secondary small">#<?= (int) $key['id'] ?> · <?= esc(lang('Api.owner')) ?> #<?= (int) $key['user_id'] ?></div></td>
                <td><?php foreach ($key['scopes'] as $scope): ?><div class="small"><?= esc(lang('Api.scopes.' . str_replace('.', '_', $scope))) ?></div><?php endforeach ?></td>
                <td><span class="badge <?= $active ? 'bg-green-lt' : 'bg-secondary-lt' ?>"><?= esc(lang($active ? 'Api.active' : 'Api.inactive')) ?></span><div class="text-secondary small"><?= esc($key['expires_at'] ?? lang('Api.noExpiry')) ?></div></td>
                <td class="small"><?= esc($key['last_used_at'] ?? '—') ?></td>
                <td class="text-end"><?php if ($key['revoked_at'] === null): ?><form method="post" action="<?= site_url('admin/api/keys/' . $key['id'] . '/revoke') ?>" onsubmit="return confirm(<?= esc(json_encode(lang('Api.revokeConfirm'), JSON_HEX_APOS | JSON_HEX_QUOT), 'attr') ?>)"><?= csrf_field() ?><button class="btn btn-outline-danger btn-sm btn-icon admin-icon-action" type="submit" aria-label="<?= esc(lang('Api.revoke'), 'attr') ?>" title="<?= esc(lang('Api.revoke'), 'attr') ?>" data-bs-toggle="tooltip" data-bs-container="body"><i class="ti ti-key-off" aria-hidden="true"></i></button></form><?php endif ?></td>
            </tr>
        <?php endforeach ?>
        <?php if ($keys === []): ?><tr><td colspan="5" class="text-secondary"><?= esc(lang('Api.noKeys')) ?></td></tr><?php endif ?>
        </tbody>
    </table></div>
</div>
<div class="card"><div class="card-body">
    <h3 class="card-title"><?= esc(lang('Api.endpoints')) ?></h3>
    <div class="text-break"><code><?= esc(site_url('api/v1')) ?></code></div>
    <div class="mt-2 text-break"><code>GET /stock · GET /stock/{variant_id} · PUT /stock/{variant_id}</code></div>
    <div class="text-break"><code>GET /orders · GET /orders/{order_id} · GET /delivery-methods</code></div>
    <p class="text-secondary mt-2 mb-0"><?= esc(lang('Api.protocolHint')) ?></p>
</div></div>
<?= $this->endSection() ?>
