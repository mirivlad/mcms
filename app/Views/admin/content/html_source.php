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
    let sourceMode = false, visualDirty = false;
    const sync = () => {
        if (!sourceMode && visualDirty) body.value = editor.innerHTML;
    };
    const changed = (fromEditorInput = false) => {
        if (sourceMode) return;
        visualDirty = true;
        sync();
        // Toolbar commands and Media insertions do not reliably generate a
        // native input event. Notify the draft workflow explicitly for them.
        if (!fromEditorInput) body.dispatchEvent(new Event('input', {bubbles:true}));
    };
    const restore = html => {
        body.value = html;
        editor.innerHTML = safeVisualHtml(html);
        visualDirty = false;
    };
    editor.addEventListener('input', () => changed(true));
    editor.addEventListener('mcms:content-sync', sync);
    editor.addEventListener('mcms:content-restore', event => restore(String(event.detail?.html ?? '')));
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
            // Switching without visual edits does not serialize or rewrite HTML.
            editor.innerHTML = safeVisualHtml(body.value);
            visualDirty = false;
            sourceMode = false;
            body.classList.add('d-none');
            editor.hidden = false;
            toggle.setAttribute('aria-pressed', 'false');
            buttons.forEach(b => { b.disabled = false; });
            if (hint) hint.hidden = true;
            editor.focus();
        }
    });
    return {sync, changed, restore};
};
</script>
