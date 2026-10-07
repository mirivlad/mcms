<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?php
$value = static function (string $name, mixed $default = '') use ($method): mixed {
    $old = old($name);
    if ($old !== null) {
        return $old;
    }

    return $method[$name] ?? $default;
};
?>

<div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-4">
    <div>
        <a href="<?= site_url('admin/delivery') ?>" class="text-muted"><?= esc(lang('Delivery.back')) ?></a>
        <h1 class="page-title mt-2"><?= esc($title) ?></h1>
    </div>
</div>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<div class="card">
    <form method="post">
        <div class="card-body">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label" for="delivery-name"><?= esc(lang('Delivery.name')) ?></label>
                <input id="delivery-name" class="form-control" name="name" maxlength="190" required
                       value="<?= esc((string) $value('name'), 'attr') ?>">
            </div>

            <div class="mb-3">
                <label class="form-label" for="delivery-type"><?= esc(lang('Delivery.typeTitle')) ?></label>
                <select id="delivery-type" class="form-select" name="type" required>
                    <?php foreach (['delivery', 'pickup'] as $type): ?>
                        <option value="<?= esc($type, 'attr') ?>" <?= (string) $value('type', 'delivery') === $type ? 'selected' : '' ?>>
                            <?= esc(lang('Delivery.type.' . $type)) ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="delivery-price"><?= esc(lang('Delivery.price')) ?></label>
                    <div class="input-group">
                        <input id="delivery-price" class="form-control" name="price" inputmode="decimal" required
                               value="<?= esc((string) $value('price', '0.00'), 'attr') ?>">
                        <span class="input-group-text">₽</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="delivery-position"><?= esc(lang('Delivery.position')) ?></label>
                    <input id="delivery-position" class="form-control" type="number" min="0" max="2000000000"
                           name="position" required value="<?= esc((string) $value('position', 100), 'attr') ?>">
                </div>
            </div>

            <label class="form-check form-switch mt-4">
                <input type="hidden" name="enabled" value="0">
                <input class="form-check-input" type="checkbox" name="enabled" value="1"
                       <?= (string) $value('enabled', 1) === '1' || $value('enabled', 1) === true ? 'checked' : '' ?>>
                <span class="form-check-label"><?= esc(lang('Delivery.enabled')) ?></span>
            </label>

            <div class="alert alert-info mt-4 mb-0"><?= esc(lang('Delivery.snapshotNotice')) ?></div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button class="btn btn-primary" type="submit"><?= esc(lang('Delivery.save')) ?></button>
            <a class="btn" href="<?= site_url('admin/delivery') ?>"><?= esc(lang('Delivery.back')) ?></a>
        </div>
    </form>
</div>

<?= $this->endSection() ?>
