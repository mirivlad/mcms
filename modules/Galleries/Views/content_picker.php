<style>
.mcms-editor-toolbar [data-gallery-picker] { flex-basis: auto; width: auto; padding: 0 .6rem; }
#content-gallery-dialog { width: min(800px, calc(100vw - 2rem)); max-height: 90vh; border: 0; border-radius: 12px; padding: 0; }
#content-gallery-dialog::backdrop { background: rgba(15, 23, 42, .45); }
#content-gallery-dialog .card-header { padding: 1rem 1.25rem; border-bottom: 1px solid var(--tblr-border-color); }
#content-gallery-dialog .card-title { margin: 0; }
#content-gallery-dialog .card-body { padding: 1.25rem; }
.content-gallery-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
.content-gallery-item { text-align: left; white-space: normal; display: flex; flex-direction: column; gap: 8px; padding: 12px; }
.content-gallery-item img { width: 100%; aspect-ratio: 2 / 1; object-fit: cover; border-radius: 6px; }
</style>
<dialog id="content-gallery-dialog" aria-labelledby="content-gallery-title">
    <div class="card-header d-flex justify-content-between gap-3">
        <h3 class="card-title" id="content-gallery-title"><?= esc(lang('Galleries.Admin.insertGallery')) ?></h3>
        <button type="button" class="btn-close" id="content-gallery-close" aria-label="<?= esc(lang('MCMS.common.cancel'), 'attr') ?>"></button>
    </div>
    <div class="card-body">
        <p class="text-secondary"><?= esc(lang('Galleries.Admin.pickerHint')) ?></p>
        <div class="mb-3">
            <label class="form-label" for="content-gallery-display"><?= esc(lang('Galleries.Admin.display')) ?></label>
            <select class="form-select" id="content-gallery-display">
                <option value="grid"><?= esc(lang('Galleries.Admin.displayGrid')) ?></option>
                <option value="slider"><?= esc(lang('Galleries.Admin.displaySlider')) ?></option>
                <option value="carousel"><?= esc(lang('Galleries.Admin.displayCarousel')) ?></option>
                <option value="cards"><?= esc(lang('Galleries.Admin.displayCards')) ?></option>
            </select>
        </div>
        <div class="d-flex gap-2 mb-3">
            <input type="search" class="form-control" id="content-gallery-query" placeholder="<?= esc(lang('Galleries.Admin.search'), 'attr') ?>" aria-label="<?= esc(lang('Galleries.Admin.search'), 'attr') ?>">
            <button type="button" class="btn btn-primary" id="content-gallery-search"><?= esc(lang('MCMS.common.search')) ?></button>
        </div>
        <p id="content-gallery-status" role="status" aria-live="polite"></p>
        <div class="content-gallery-grid" id="content-gallery-results"></div>
        <div class="d-flex justify-content-between align-items-center gap-2 mt-3">
            <button type="button" class="btn" id="content-gallery-previous" aria-label="<?= esc(lang('Galleries.Admin.previousPage'), 'attr') ?>">←</button>
            <span id="content-gallery-page"></span>
            <button type="button" class="btn" id="content-gallery-next" aria-label="<?= esc(lang('Galleries.Admin.nextPage'), 'attr') ?>">→</button>
        </div>
    </div>
</dialog>
<script>
(() => {
    const trigger = document.querySelector('[data-gallery-picker]');
    const editor = document.getElementById(trigger.dataset.editor);
    const dialog = document.getElementById('content-gallery-dialog');
    const query = document.getElementById('content-gallery-query');
    const display = document.getElementById('content-gallery-display');
    const status = document.getElementById('content-gallery-status');
    const results = document.getElementById('content-gallery-results');
    const previous = document.getElementById('content-gallery-previous');
    const next = document.getElementById('content-gallery-next');
    const pageLabel = document.getElementById('content-gallery-page');
    const messages = <?= json_encode([
        'loading' => lang('Galleries.Admin.pickerLoading'), 'empty' => lang('Galleries.Admin.pickerEmpty'),
        'error' => lang('Galleries.Admin.pickerError'),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    let savedRange = null;
    let page = 1;
    let lastPage = 1;
    let requestId = 0;
    const rememberSelection = () => {
        const selection = window.getSelection();
        if (selection && selection.rangeCount && editor.contains(selection.getRangeAt(0).commonAncestorContainer)) {
            savedRange = selection.getRangeAt(0).cloneRange();
        }
    };
    ['keyup', 'mouseup', 'blur'].forEach(event => editor.addEventListener(event, rememberSelection));
    trigger.addEventListener('mousedown', rememberSelection);
    const insert = id => {
        if (!Number.isSafeInteger(id) || id < 1) return;
        dialog.close();
        editor.focus();
        const range = savedRange && editor.contains(savedRange.commonAncestorContainer) ? savedRange : document.createRange();
        if (range !== savedRange) {
            range.selectNodeContents(editor);
            range.collapse(false);
        }
        range.deleteContents();
        const mode = ['slider', 'carousel', 'cards'].includes(display.value) ? ' view="' + display.value + '"' : '';
        const text = document.createTextNode('[gallery id="' + id + '"' + mode + ']');
        range.insertNode(text);
        range.setStartAfter(text);
        range.collapse(true);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
        rememberSelection();
        editor.dispatchEvent(new Event('input', {bubbles: true}));
    };
    const load = async targetPage => {
        const currentRequest = ++requestId;
        results.replaceChildren();
        status.textContent = messages.loading;
        pageLabel.textContent = '';
        previous.disabled = next.disabled = true;
        try {
            const url = new URL(<?= json_encode(site_url('admin/galleries/picker')) ?>);
            url.searchParams.set('q', query.value.trim());
            url.searchParams.set('page', targetPage);
            const response = await fetch(url, {headers: {'Accept': 'application/json'}, credentials: 'same-origin', cache: 'no-store'});
            if (!response.ok) throw new Error('gallery-picker');
            const data = await response.json();
            if (currentRequest !== requestId || !dialog.open) return;
            page = data.page;
            lastPage = data.lastPage;
            status.textContent = data.items.length ? '' : messages.empty;
            data.items.forEach(item => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn content-gallery-item';
                if (item.thumbnail_url) {
                    const image = document.createElement('img');
                    image.src = item.thumbnail_url;
                    image.alt = '';
                    image.loading = 'lazy';
                    button.appendChild(image);
                }
                const title = document.createElement('span');
                title.textContent = item.title;
                button.appendChild(title);
                button.addEventListener('click', () => insert(Number(item.id)));
                results.appendChild(button);
            });
            pageLabel.textContent = page + ' / ' + lastPage;
            previous.disabled = page <= 1;
            next.disabled = page >= lastPage;
        } catch (error) {
            if (currentRequest === requestId && dialog.open) status.textContent = messages.error;
        }
    };
    trigger.addEventListener('click', () => {
        rememberSelection();
        query.value = '';
        dialog.showModal();
        query.focus();
        load(1);
    });
    document.getElementById('content-gallery-close').addEventListener('click', () => dialog.close());
    document.getElementById('content-gallery-search').addEventListener('click', () => load(1));
    query.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            load(1);
        }
    });
    previous.addEventListener('click', () => load(page - 1));
    next.addEventListener('click', () => load(page + 1));
})();
</script>
