<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <span class="text-secondary" role="status" data-autosave-status><?= esc(lang('MCMS.content.autosave')) ?></span>
    <button class="btn btn-sm btn-outline-secondary" type="button" data-autosave-retry hidden><?= esc(lang('MCMS.content.retry')) ?></button>
    <?php if ($contentId > 0): ?>
    <button class="btn btn-sm btn-outline-secondary ms-auto" type="button" data-content-history aria-haspopup="dialog"><?= esc(lang('MCMS.content.history')) ?></button>
    <?php endif ?>
</div>
<div class="alert alert-info" data-draft-recovery hidden>
    <p data-draft-date></p>
    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-primary" type="button" data-draft-restore><?= esc(lang('MCMS.content.restore')) ?></button>
        <button class="btn btn-outline-secondary" type="button" data-draft-discard><?= esc(lang('MCMS.content.discard')) ?></button>
    </div>
</div>
<dialog id="content-history-dialog" aria-labelledby="content-history-heading" style="width:min(760px,94vw);max-height:85vh;border:0;border-radius:12px;background:var(--tblr-bg-surface);color:var(--tblr-body-color)">
    <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
        <h3 id="content-history-heading"><?= esc(lang('MCMS.content.history')) ?></h3>
        <button class="btn-close" type="button" data-history-close aria-label="<?= esc(lang('MCMS.common.cancel'), 'attr') ?>"></button>
    </div>
    <p class="text-secondary"><?= esc(lang('MCMS.content.restored')) ?></p>
    <div data-history-items role="status"></div>
    <button class="btn btn-outline-secondary mt-3" type="button" data-history-more hidden><?= esc(lang('MCMS.content.moreVersions')) ?></button>
</dialog>
<dialog id="content-restore-dialog" aria-labelledby="content-restore-heading" style="width:min(500px,94vw);border:0;border-radius:12px;background:var(--tblr-bg-surface);color:var(--tblr-body-color)">
    <h3 id="content-restore-heading"><?= esc(lang('MCMS.content.restore')) ?></h3>
    <p><?= esc(lang('MCMS.content.restoreConfirm')) ?></p>
    <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn" data-restore-cancel><?= esc(lang('MCMS.common.cancel')) ?></button>
        <button type="button" class="btn btn-primary" data-restore-confirm><?= esc(lang('MCMS.content.restore')) ?></button>
    </div>
</dialog>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-content-preview]').closest('form');
    const editor = document.getElementById(<?= json_encode($editorId) ?>);
    const root = <?= json_encode($workflowUrl) ?>;
    const id = <?= (int) $contentId ?>;
    const labels = <?= json_encode(lang('MCMS.content'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const status = form.querySelector('[data-autosave-status]');
    const retry = form.querySelector('[data-autosave-retry]');
    const recovery = form.querySelector('[data-draft-recovery]');
    let draft = null;
    let ready = false;
    let dirty = false;
    let submitting = false;
    let timer;
    let generation = 0;
    let synchronizing = false;
    let saving = false;
    function fields() {
        synchronizing = true;
        editor.dispatchEvent(new Event('input', {bubbles:true}));
        synchronizing = false;
        const body = new FormData(form);
        body.set('content_id', String(id));
        return body;
    }
    async function read(url) {
        const response = await fetch(url, {credentials:'same-origin', cache:'no-store', headers:{Accept:'application/json'}});
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || labels.saveFailed);
        return data;
    }
    function schedule() {
        if (submitting || synchronizing) return;
        dirty = true;
        ++generation;
        clearTimeout(timer);
        if (ready) timer = setTimeout(save, 2500);
    }
    async function save() {
        if (!ready || !dirty || submitting || saving) return;
        saving = true;
        clearTimeout(timer);
        // Synchronization fires input; capture the generation afterwards.
        const body = fields();
        const current = generation;
        status.textContent = labels.saving;
        retry.hidden = true;
        try {
            await form.mcmsRequest(root + '/draft', body);
            if (current === generation) {
                dirty = false;
                clearTimeout(timer);
                status.textContent = labels.saved;
            }
        } catch (failure) {
            status.textContent = labels.saveFailed;
            retry.hidden = false;
        } finally {
            saving = false;
            if (dirty && current !== generation && !submitting) timer = setTimeout(save, 2500);
        }
    }
    const confirmation = document.getElementById('content-restore-dialog');
    confirmation.querySelector('[data-restore-cancel]').addEventListener('click', () => confirmation.close('cancel'));
    confirmation.querySelector('[data-restore-confirm]').addEventListener('click', () => confirmation.close('restore'));
    function confirmRestore() {
        confirmation.returnValue = 'cancel';
        confirmation.showModal();
        return new Promise(resolve => confirmation.addEventListener('close', () => resolve(confirmation.returnValue === 'restore'), {once:true}));
    }
    function restore(data) {
        for (const name of ['title','slug','status','seo_title','seo_description','seo_keywords','tags','excerpt','published_at','featured_media_id','category_id']) {
            const field = form.elements.namedItem(name);
            if (!field) continue;
            let value = data[name] ?? '';
            if (field.type === 'datetime-local') value = String(value).replace(' ', 'T').slice(0,16);
            field.value = value;
            field.dispatchEvent(new Event('change', {bubbles:true}));
        }
        editor.innerHTML = data.body || '';
        editor.dispatchEvent(new Event('input', {bubbles:true}));
        const featured = document.getElementById('featured-preview-image');
        if (featured) {
            featured.src = data.featured_image_url || '';
            document.getElementById('featured-preview').classList.toggle('d-none', !data.featured_image_url);
            document.getElementById('remove-featured').classList.toggle('d-none', !data.featured_media_id);
        }
        status.textContent = labels.restored;
    }
    form.addEventListener('input', schedule);
    form.addEventListener('change', schedule);
    form.addEventListener('mcms:submit', () => {submitting = true; clearTimeout(timer);});
    window.addEventListener('beforeunload', event => {if (dirty && !submitting) {event.preventDefault(); event.returnValue = '';}});
    retry.addEventListener('click', () => ready ? save() : loadDraft());
    async function loadDraft() {
        try {
            draft = (await read(root + '/draft?content_id=' + id)).draft;
            if (draft) {
                recovery.hidden = false;
                recovery.querySelector('[data-draft-date]').textContent = labels.recovery + ' ' + draft.updated_at;
            } else {
                ready = true;
                if (dirty) schedule();
            }
            retry.hidden = true;
        } catch (failure) {
            status.textContent = labels.saveFailed;
            retry.hidden = false;
        }
    }
    recovery.querySelector('[data-draft-restore]').addEventListener('click', async () => {
        if (dirty && !await confirmRestore()) return;
        ready = true;
        recovery.hidden = true;
        restore(draft.data);
        schedule();
    });
    recovery.querySelector('[data-draft-discard]').addEventListener('click', async event => {
        event.target.disabled = true;
        try {
            const body = new FormData(); body.set('content_id', String(id));
            await form.mcmsRequest(root + '/draft/discard', body);
            recovery.hidden = true;
            ready = true;
            if (dirty) schedule();
        } catch (failure) { status.textContent = labels.saveFailed; }
        finally {event.target.disabled = false;}
    });
    const historyButton = form.querySelector('[data-content-history]');
    if (historyButton) {
        const dialog = document.getElementById('content-history-dialog');
        const items = dialog.querySelector('[data-history-items]');
        const more = dialog.querySelector('[data-history-more]');
        let offset = 0;
        async function loadHistory() {
            more.disabled = true;
            try {
                const data = await read(root + '/' + id + '/history?offset=' + offset);
                if (!data.count) items.textContent = labels.emptyHistory;
                for (const item of data.items) {
                    const row = document.createElement('div');
                    row.className = 'border-bottom py-3 d-flex flex-wrap align-items-center gap-2';
                    const text = document.createElement('span');
                    text.className = 'flex-fill';
                    text.textContent = '#' + item.id + ' · ' + item.created_at + ' · ' + labels.author + ' ' + (item.created_by ?? '—') + ' · ' + item.title;
                    const button = document.createElement('button');
                    button.type = 'button'; button.className = 'btn btn-sm btn-outline-primary'; button.textContent = labels.restore;
                    button.addEventListener('click', async () => {
                        if (!await confirmRestore()) return;
                        button.disabled = true;
                        try {
                            const version = await read(root + '/' + id + '/history/' + item.id);
                            ready = true; recovery.hidden = true;
                            restore(version.data); schedule(); dialog.close();
                        } catch (failure) {status.textContent = failure.message;}
                        finally {button.disabled = false;}
                    });
                    row.append(text, button); items.append(row);
                }
                offset += data.items.length;
                more.hidden = offset >= data.count;
            } catch (failure) {items.textContent = failure.message;}
            finally {more.disabled = false;}
        }
        historyButton.addEventListener('click', () => {offset = 0; items.replaceChildren(); more.hidden = true; dialog.showModal(); loadHistory();});
        dialog.querySelector('[data-history-close]').addEventListener('click', () => dialog.close());
        dialog.addEventListener('close', () => historyButton.focus());
        more.addEventListener('click', loadHistory);
    }
    loadDraft();
});
</script>
