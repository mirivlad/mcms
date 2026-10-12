<style>
#content-preview-dialog { width: calc(100vw - 2rem); max-width: 1440px; height: 90vh; max-height: 90vh; padding: 0; border: 0; border-radius: 12px; background: var(--tblr-bg-surface); color: var(--tblr-body-color); }
#content-preview-dialog[open] { display: flex; flex-direction: column; }
#content-preview-dialog::backdrop { background: rgba(15, 23, 42, .45); }
#content-preview-dialog .card-header { padding: 1rem; flex-shrink: 0; }
#content-preview-frame { width: 100%; flex: 1; min-height: 0; border: 0; background: white; }
</style>
<div class="d-flex justify-content-end mb-3">
    <button type="button" class="btn btn-outline-primary" data-content-preview aria-haspopup="dialog" aria-controls="content-preview-dialog"><i class="ti ti-eye me-1"></i><?= esc(lang('MCMS.content.preview')) ?></button>
</div>
<p class="alert alert-danger" role="alert" data-preview-error hidden></p>
<dialog id="content-preview-dialog" aria-labelledby="content-preview-heading">
    <div class="card-header d-flex justify-content-between gap-3">
        <h3 class="card-title mb-0" id="content-preview-heading"><?= esc(lang('MCMS.content.preview')) ?></h3>
        <button type="button" class="btn-close" data-preview-close aria-label="<?= esc(lang('MCMS.common.cancel'), 'attr') ?>"></button>
    </div>
    <p class="px-3" role="status" data-preview-loading><?= esc(lang('MCMS.content.previewLoading')) ?></p>
    <iframe id="content-preview-frame" sandbox="allow-scripts" title="<?= esc(lang('MCMS.content.preview'), 'attr') ?>"></iframe>
</dialog>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const button = document.querySelector('[data-content-preview]');
    const form = button.closest('form');
    const error = form.querySelector('[data-preview-error]');
    const editor = document.getElementById(<?= json_encode($editorId) ?>);
    const dialog = document.getElementById('content-preview-dialog');
    const frame = document.getElementById('content-preview-frame');
    const loading = dialog.querySelector('[data-preview-loading]');
    let requestId = 0;
    let pending = false;
    form.addEventListener('submit', event => { if (pending) event.preventDefault(); });
    dialog.querySelector('[data-preview-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
        ++requestId;
        frame.srcdoc = '';
        if (!pending) {
            button.disabled = false;
            button.focus();
        }
    });
    button.addEventListener('click', async () => {
        error.hidden = true;
        loading.hidden = false;
        const currentRequest = ++requestId;
        pending = true;
        button.disabled = true;
        const submitButtons = Array.from(form.querySelectorAll('button[type="submit"]')).map(button => [button, button.disabled]);
        submitButtons.forEach(([button]) => button.disabled = true);
        dialog.showModal();
        editor.dispatchEvent(new Event('mcms:content-sync'));
        try {
            const data = await form.mcmsRequest(<?= json_encode($previewUrl) ?>, new FormData(form));
            if (currentRequest !== requestId || !dialog.open) return;
            if (!data.html) throw new Error(data.error || <?= json_encode(lang('MCMS.content.previewFailed')) ?>);
            frame.srcdoc = data.html;
            loading.hidden = true;
        } catch (failure) {
            if (currentRequest !== requestId || !dialog.open) return;
            error.textContent = failure.message;
            error.hidden = false;
            dialog.close();
        } finally {
            pending = false;
            button.disabled = false;
            submitButtons.forEach(([button, disabled]) => button.disabled = disabled);
            if (!dialog.open) button.focus();
        }
    });
});
</script>
