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
    return {sync, changed, restore, format, formatBlock, link, insertImage, engine};
};
</script>
