<script src="<?= base_url('assets/vendor/squire/squire.min.js') ?>"></script>
<style>
.mcms-html-code {
    position: relative;
    height: min(68vh, 700px);
    min-height: 380px;
    border: 1px solid var(--tblr-border-color);
    border-radius: var(--tblr-border-radius);
    font-size: 14px;
}
.mcms-html-code .ace_content { font-variant-ligatures: none; }
.mcms-editor-toolbar [data-html-format] { flex: 0 0 auto; width: auto; padding: 0 .65rem; }
.mcms-editor-toolbar [data-html-format][hidden] { display: none !important; }
@media (max-width: 600px) { .mcms-html-code { height: 60vh; min-height: 300px; } }
</style>
<script src="<?= base_url('assets/vendor/ace/ace.js') ?>"></script>
<script src="<?= base_url('assets/vendor/ace/mode-html.js') ?>"></script>
<script src="<?= base_url('assets/vendor/ace/mode-css.js') ?>"></script>
<script src="<?= base_url('assets/vendor/ace/mode-javascript.js') ?>"></script>
<script src="<?= base_url('assets/vendor/ace/theme-chrome.js') ?>"></script>
<script src="<?= base_url('assets/vendor/ace/ext-language_tools.js') ?>"></script>
<script src="<?= base_url('assets/vendor/ace/ext-beautify.js') ?>"></script>
<script>
/* The textarea is the canonical HTML; the browser DOM is a visual representation. */
window.mcmsCreateHtmlSourceEditor = (editor, body, toolbar) => {
    const toggle = toolbar.querySelector('[data-html-source-toggle]');
    const buttons = Array.from(toolbar.querySelectorAll('button')).filter(b => b !== toggle && !b.hasAttribute('data-html-format'));
    const hint = body.parentElement.querySelector('[data-html-source-hint]');
    const codeContainer = body.parentElement.querySelector('[data-html-code]');
    const formatButton = toolbar.querySelector('[data-html-format]');
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
    // ACE is lazily initialized when the user first enters HTML mode.
    // Formatting the *displayed* source must not modify the canonical textarea.
    let codeEditor = null;
    let loadingSource = false;
    let sourceEdited = false;
    const cssProperties = [
        'background','background-color','border','border-color','border-radius','border-style',
        'border-width','box-shadow','color','display','flex','flex-direction','flex-wrap',
        'font-family','font-size','font-style','font-weight','gap','grid-template-columns',
        'height','justify-content','align-items','line-height','margin','margin-top',
        'margin-bottom','max-width','min-height','opacity','overflow','padding','padding-top',
        'position','text-align','text-decoration','transform','transition','width','z-index'
    ];
    const htmlTags = ['a','article','aside','b','blockquote','br','caption','code','dd',
        'div','dl','dt','em','figcaption','figure','footer','h2','h3','h4','h5','h6',
        'header','hr','i','img','li','main','nav','ol','p','pre','section','span','strong',
        'table','tbody','td','th','thead','tr','u','ul'];
    const commonAttrs = ['alt','aria-label','aria-hidden','class','colspan','height','href',
        'id','lang','rel','role','rowspan','src','target','title','width'];
    const attrMap = {
        a:['href','target','rel','title','class'], img:['src','alt','width','height','class'],
        table:['class'], td:['colspan','rowspan','class'], th:['colspan','rowspan','class']
    };
    const htmlCssCompleter = {
        getCompletions(aceEditor, session, pos, prefix, callback) {
            const before = session.getLine(pos.row).slice(0, pos.column);
            // HTML mode can embed CSS (inside style= or <style>). Whether CSS
            // persists is decided by the server-side allowlist.
            const cssContext = /style\s*=\s*["'][^"']*$/i.test(before)
                || (session.getLines(Math.max(0, pos.row - 40), pos.row).join('\n') + '\n' + before)
                    .match(/<style\b[^>]*>(?:(?!<\/style>).)*$/is);
            let choices = [];
            if (cssContext) {
                choices = cssProperties.map(x => ({caption:x, value:x, meta:'CSS', score:1200}));
                choices.push(...['auto','block','flex','grid','inline','none','relative','absolute',
                    'center','left','right','inherit','initial','transparent','solid'].map(x =>
                    ({caption:x,value:x,meta:'CSS value',score:800})));
            } else if (/<\/?[\w:-]*$/.test(before)) {
                choices = htmlTags.map(x => ({caption:x,value:x,meta:'HTML tag',score:1100}));
            } else {
                const match = before.match(/<([a-z][\w:-]*)\b[^<>]*$/i);
                if (match) {
                    choices = [...new Set([...commonAttrs, ...(attrMap[match[1].toLowerCase()] || [])])]
                        .map(x => ({caption:x,value:x + '=""',meta:'attribute',score:1100}));
                }
            }
            callback(null, choices);
        }
    };
    const loadCode = () => {
        if (!codeContainer || !window.ace) return false;
        if (codeEditor) return true;
        const base = <?= json_encode(base_url('assets/vendor/ace/'), JSON_UNESCAPED_SLASHES) ?>;
        window.ace.config.set('basePath', base);
        codeEditor = window.ace.edit(codeContainer, {
            mode: 'ace/mode/html',
            theme: 'ace/theme/chrome',
            useWorker: false,
            enableBasicAutocompletion: true,
            enableLiveAutocompletion: true,
            enableSnippets: false,
            highlightActiveLine: true,
            showPrintMargin: false,
            tabSize: 4,
            useSoftTabs: false,
            wrap: true,
            behavioursEnabled: true
        });
        codeEditor.completers.unshift(htmlCssCompleter);
        codeEditor.commands.addCommand({
            name: 'formatHTML', bindKey: {win:'Ctrl-Shift-F', mac:'Command-Shift-F'},
            exec() { formatHtml(true); }, readOnly: false
        });
        codeEditor.session.on('change', () => {
            if (!loadingSource && sourceMode) {
                sourceEdited = true;
                body.value = codeEditor.getValue();
                body.dispatchEvent(new Event('input', {bubbles:true}));
            }
        });
        return true;
    };
    const formatHtml = (intentional = false) => {
        if (!codeEditor) return;
        const previous = loadingSource;
        loadingSource = !intentional;
        try {
            window.ace.require('ace/ext/beautify').beautify(codeEditor.session);
        } finally {
            loadingSource = previous;
        }
        if (intentional) {
            sourceEdited = true;
            body.value = codeEditor.getValue();
            body.dispatchEvent(new Event('input', {bubbles:true}));
        }
    };
    const showSource = () => {
        sourceEdited = false;
        const hasAce = loadCode();
        if (hasAce) {
            loadingSource = true;
            try {
                codeEditor.setValue(body.value, -1);
                codeEditor.session.setUseWorker(false);
                formatHtml();
                codeEditor.session.getUndoManager().reset();
            } finally {
                loadingSource = false;
            }
            codeContainer.classList.remove('d-none');
            codeEditor.resize();
            codeEditor.focus();
        } else {
            body.classList.remove('d-none');
            body.focus();
        }
        if (formatButton) formatButton.hidden = !hasAce;
    };
    const hideSource = () => {
        if (codeContainer) codeContainer.classList.add('d-none');
        body.classList.add('d-none');
        if (formatButton) formatButton.hidden = true;
    };
    if (formatButton) formatButton.addEventListener('click', () => formatHtml(true));

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
        if (sourceMode) showSource();
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
        tableDialog.querySelector('[data-table-cancel]').addEventListener('click', () => tableDialog.close());
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
            toggle.setAttribute('aria-pressed', 'true');
            buttons.forEach(b => { b.disabled = true; });
            if (hint) hint.hidden = false;
            showSource();
        } else {
            // Squire normalizes the *visual* DOM; the source is not rewritten.
            hydrate(body.value);
            sourceMode = false;
            hideSource();
            editor.hidden = false;
            toggle.setAttribute('aria-pressed', 'false');
            buttons.forEach(b => { b.disabled = false; });
            if (hint) hint.hidden = true;
            engine.focus();
        }
    });
    return {sync, changed, restore, format, formatBlock, link, insertImage, insertTable, engine,
        get codeEditor() { return codeEditor; }, formatHtml };
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
            <button type="button" class="btn" data-table-cancel><?= esc(lang('MCMS.common.cancel')) ?></button>
            <button type="submit" class="btn btn-primary"><?= esc(lang('MCMS.content.insertTable')) ?></button>
        </div>
    </form>
</dialog>
