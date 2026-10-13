<script src="<?= base_url('assets/vendor/squire/squire.min.js') ?>"></script>
<script>
/* The textarea is the canonical HTML; the browser DOM is a visual representation. */
window.mcmsCreateHtmlSourceEditor = (editor, body, toolbar) => {
    const toggle = toolbar.querySelector('[data-html-source-toggle]');
    const buttons = Array.from(toolbar.querySelectorAll('button')).filter(b => b !== toggle);
    const hint = body.parentElement.querySelector('[data-html-source-hint]');
    const tags = new Set(['p','br','h2','h3','h4','h5','h6','ul','ol','li','blockquote',
        'pre','code','strong','b','em','i','u','s','a','img','figure','figcaption',
        'hr','table','thead','tbody','tr','th','td','div','span']);
    const dropped = new Set(['script','style','iframe','object','embed','form','input',
        'button','textarea','select','option','svg','math']);
    const attrs = {
        a: new Set(['href','title','target','rel']),
        img: new Set(['src','alt','title','width','height','class']),
        th: new Set(['colspan','rowspan','class']),
        td: new Set(['colspan','rowspan','class'])
    };
    const urlSafe = (value, mailto) => {
        if (!value || value.startsWith('/') || value.startsWith('#')) return true;
        try {
            const scheme = new URL(value, window.location.href).protocol.toLowerCase();
            return ['http:','https:'].includes(scheme) || (mailto && scheme === 'mailto:');
        } catch (_) { return false; }
    };
    // A detached template keeps active HTML out of the admin page; the PHP
    // sanitizer remains authoritative when saving and generating previews.
    const safeVisualHtml = html => {
        const template = document.createElement('template');
        template.innerHTML = html;
        const clean = parent => {
            for (const node of Array.from(parent.childNodes)) {
                if (node.nodeType === Node.COMMENT_NODE) { node.remove(); continue; }
                if (node.nodeType !== Node.ELEMENT_NODE) continue;
                const tag = node.localName;
                if (dropped.has(tag)) { node.remove(); continue; }
                clean(node);
                if (!tags.has(tag)) { node.replaceWith(...Array.from(node.childNodes)); continue; }
                const permitted = attrs[tag] || new Set(['class']);
                for (const attr of Array.from(node.attributes)) {
                    const key = attr.name.toLowerCase(), value = attr.value.trim();
                    if (!permitted.has(key)
                        || (key === 'href' && !urlSafe(value, true))
                        || (key === 'src' && !urlSafe(value, false))
                        || (key === 'target' && !['_blank','_self'].includes(value))
                        || (['width','height','colspan','rowspan'].includes(key) && !/^[1-9][0-9]{0,3}$/.test(value))
                        || (key === 'class' && !value.split(/\s+/).every(c => /^[a-zA-Z0-9_-]{1,64}$/.test(c)))) {
                        node.removeAttribute(attr.name);
                    }
                }
                if (tag === 'a' && node.getAttribute('target') === '_blank') {
                    node.setAttribute('rel', 'noopener noreferrer');
                }
            }
        };
        clean(template.content);
        return template.innerHTML;
    };
    let sourceMode = false;
    let visualDirty = false;
    let hydrating = false;
    const safeFragment = html => {
        const template = document.createElement('template');
        template.innerHTML = safeVisualHtml(html);
        return template.content;
    };
    const engine = new window.Squire(editor, {
        blockTag: 'P',
        // Never pass untrusted raw HTML directly to Squire or contenteditable.
        sanitizeToDOMFragment: safeFragment,
        addLinks: false
    });
    const hydrate = html => {
        hydrating = true;
        try {
            engine.setHTML(html);
            visualDirty = false;
        } finally {
            hydrating = false;
        }
    };
    hydrate(body.value);
    const sync = () => {
        if (!sourceMode && visualDirty) body.value = engine.getHTML();
    };
    const changed = () => {
        if (hydrating || sourceMode) return;
        visualDirty = true;
        sync();
        // The draft workflow observes form input; not all editor commands emit
        // a native, bubbling DOM input event.
        body.dispatchEvent(new Event('input', {bubbles: true}));
    };
    engine.addEventListener('input', changed);
    // Other modules (Galleries picker) insert DOM nodes and dispatch a native event.
    editor.addEventListener('input', changed);
    editor.addEventListener('mcms:content-sync', sync);
    const restore = html => {
        body.value = html;
        hydrate(html);
    };
    editor.addEventListener('mcms:content-restore', event => restore(String(event.detail?.html ?? '')));

    const format = command => {
        if (sourceMode) return;
        switch (command) {
            case 'bold':
                if (engine.hasFormat('B') || engine.hasFormat('STRONG')) engine.removeBold();
                else engine.bold();
                break;
            case 'italic':
                if (engine.hasFormat('I') || engine.hasFormat('EM')) engine.removeItalic();
                else engine.italic();
                break;
            case 'insertUnorderedList':
                if (engine.hasFormat('UL')) engine.removeList();
                else engine.makeUnorderedList();
                break;
            case 'insertOrderedList':
                if (engine.hasFormat('OL')) engine.removeList();
                else engine.makeOrderedList();
                break;
            case 'removeFormat':
                engine.removeAllFormatting();
                break;
            case 'undo':
                engine.undo();
                break;
            case 'redo':
                engine.redo();
                break;
            default:
                throw new Error('Unknown rich-text command: ' + command);
        }
        changed();
    };
    const formatBlock = tag => {
        if (sourceMode) return;
        if (tag === 'blockquote') {
            if (engine.hasFormat('BLOCKQUOTE')) engine.decreaseQuoteLevel();
            else engine.increaseQuoteLevel();
        } else if (['p', 'h2'].includes(tag)) {
            const name = tag.toUpperCase();
            engine.modifyBlocks(fragment => {
                for (const block of Array.from(fragment.childNodes)) {
                    if (block.nodeType !== Node.ELEMENT_NODE) continue;
                    if (!/^(?:P|DIV|H[2-6])$/.test(block.nodeName)) continue;
                    if (block.nodeName === name) continue;
                    const replacement = document.createElement(name);
                    while (block.firstChild) replacement.appendChild(block.firstChild);
                    block.replaceWith(replacement);
                }
                return fragment;
            });
        }
        changed();
    };
    const link = url => {
        if (sourceMode || !url) return;
        const value = url.trim();
        if (!urlSafe(value, true)) return;
        engine.makeLink(value);
        changed();
    };
    const insertImage = item => {
        if (sourceMode || !item || !urlSafe(String(item.url || '').trim(), false)) return;
        const paragraph = document.createElement('p');
        const img = document.createElement('img');
        img.src = item.url;
        img.alt = item.alt_text || item.title || item.original_name || '';
        img.className = 'img-fluid';
        paragraph.appendChild(img);
        // Squire inserts structured HTML with proper undo state and selection.
        engine.insertHTML(paragraph.outerHTML);
        changed();
    };
    const tableDialog = document.getElementById('mcms-insert-table-dialog');
    const tableTrigger = toolbar.querySelector('[data-insert-table]');
    const insertTable = (rows, columns) => {
        if (sourceMode || !Number.isInteger(rows) || !Number.isInteger(columns)
            || rows < 1 || rows > 12 || columns < 1 || columns > 12) return;
        const cells = Array.from({length: rows}, () =>
            '<tr>' + '<td>&nbsp;</td>'.repeat(columns) + '</tr>'
        ).join('');
        engine.focus();
        engine.insertHTML('<table class="table table-bordered"><tbody>' + cells + '</tbody></table>');
        changed();
    };
    if (tableDialog && tableTrigger) {
        const tableForm = tableDialog.querySelector('form');
        tableTrigger.addEventListener('click', () => tableDialog.showModal());
        tableForm.addEventListener('submit', event => {
            event.preventDefault();
            const rows = Number(tableForm.elements.namedItem('rows').value);
            const columns = Number(tableForm.elements.namedItem('columns').value);
            if (!tableForm.reportValidity()) return;
            tableDialog.close();
            insertTable(rows, columns);
        });
    }
    toggle.addEventListener('click', () => {
        if (!sourceMode) {
            sync();
            sourceMode = true;
            editor.hidden = true;
            body.classList.remove('d-none');
            toggle.setAttribute('aria-pressed', 'true');
            buttons.forEach(b => { b.disabled = true; });
            if (hint) hint.hidden = false;
            body.focus();
        } else {
            // Squire normalizes the *visual* DOM; the source is not rewritten.
            hydrate(body.value);
            sourceMode = false;
            body.classList.add('d-none');
            editor.hidden = false;
            toggle.setAttribute('aria-pressed', 'false');
            buttons.forEach(b => { b.disabled = false; });
            if (hint) hint.hidden = true;
            engine.focus();
        }
    });
    return {sync, changed, restore, format, formatBlock, link, insertImage, insertTable, engine};
};
</script>
<dialog id="mcms-insert-table-dialog" aria-labelledby="mcms-insert-table-label" class="rounded-3 border p-4" style="width:min(420px,94vw);background:var(--tblr-bg-surface);color:var(--tblr-body-color)">
    <h3 id="mcms-insert-table-label" class="mb-3"><?= esc(lang('MCMS.content.insertTable')) ?></h3>
    <form method="dialog">
        <div class="row g-3">
            <div class="col-6">
                <label for="mcms-table-rows" class="form-label"><?= esc(lang('MCMS.content.tableRows')) ?></label>
                <input id="mcms-table-rows" name="rows" type="number" min="1" max="12" value="3" required class="form-control">
            </div>
            <div class="col-6">
                <label for="mcms-table-columns" class="form-label"><?= esc(lang('MCMS.content.tableColumns')) ?></label>
                <input id="mcms-table-columns" name="columns" type="number" min="1" max="12" value="3" required class="form-control">
            </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn" onclick="this.closest('dialog').close()"><?= esc(lang('MCMS.common.cancel')) ?></button>
            <button type="submit" class="btn btn-primary"><?= esc(lang('MCMS.content.insertTable')) ?></button>
        </div>
    </form>
</dialog>
