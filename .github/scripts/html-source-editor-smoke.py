#!/usr/bin/env python3
"""Headless browser regression for the real mCMS HTML source-mode controller.

Requires Chrome/Chromium; no external services, JavaScript packages or CDNs.
"""
from __future__ import annotations

import html
import pathlib
import re
import shutil
import subprocess
import tempfile

ROOT = pathlib.Path(__file__).resolve().parents[2]
SOURCE = ROOT / "app/Views/admin/content/html_source.php"


def main() -> None:
    chrome = next((path for name in ("google-chrome", "chromium", "chromium-browser")
                   if (path := shutil.which(name))), None)
    if chrome is None:
        raise SystemExit("HTML editor browser smoke requires Chrome/Chromium")
    widget = SOURCE.read_text(encoding="utf-8")
    vendor = ROOT / "public/assets/vendor/squire/squire.min.js"
    if not vendor.exists() or "window.Squire" not in vendor.read_text(encoding="utf-8"):
        raise SystemExit("Vendored Squire engine is missing")
    if "mcmsCreateHtmlSourceEditor" not in widget or "data-html-code" not in widget:
        # The code editor is attached to the form, not inside the controller.
        assert "window.ace" in widget, "Ace source controller missing"
    # Use file:// for every locally vendored PHP asset; no CDN requests.
    assets = ROOT / "public/assets/vendor"
    asset_re = re.compile(r'<script src="<\?= base_url\(\'assets/vendor/([^\']+)\'\) \?>"></script>')
    widget = asset_re.sub(lambda m: '<script src="' + (assets / m.group(1)).as_uri()
                          + '"></script>', widget)
    widget = widget.replace(
        "<?= json_encode(base_url('assets/vendor/ace/'), JSON_UNESCAPED_SLASHES) ?>",
        repr((assets / "ace").as_uri() + "/"),
    )
    if "<?=" in widget.split("<dialog", 1)[0]:
        raise SystemExit("Browser fixture contains unresolved PHP placeholders")

    for filename in ("modules/Pages/Views/form.php", "modules/News/Views/form.php"):
        content = (ROOT / filename).read_text(encoding="utf-8")
        assert "data-html-source-toggle" in content, filename
        assert "mcmsCreateHtmlSourceEditor" in content, filename
        assert "const visualChanged = htmlEditor.changed" in content, filename
    workflow = (ROOT / "app/Views/admin/content/workflow.php").read_text(encoding="utf-8")
    preview = (ROOT / "app/Views/admin/content/preview.php").read_text(encoding="utf-8")
    assert "mcms:content-restore" in workflow
    assert "mcms:content-sync" in workflow and "mcms:content-sync" in preview

    source = '<p class="kept" data-other="preserve">A &amp; B</p>\n<!-- keep comment -->'
    assertions = r'''
<script>
const errors = [];
function check(condition, name) { if (!condition) errors.push(name); }
try {
    const editor = document.getElementById('visual');
    const body = document.getElementById('source');
    const toolbar = document.getElementById('toolbar');
    const toggle = document.getElementById('toggle');
    const format = document.getElementById('format');
    const pretty = document.getElementById('pretty');
    const codePanel = document.getElementById('code');
    const initial = body.value;
    const controller = window.mcmsCreateHtmlSourceEditor(editor, body, toolbar);
    document.execCommand = () => { throw Error('Deprecated execCommand called'); };

    toggle.click();
    check(body.value === initial, 'source-toggle changed initial HTML');
    check(editor.hidden && body.classList.contains('d-none') && !codePanel.classList.contains('d-none'),
        'Ace source layout');
    check(controller.codeEditor && controller.codeEditor.session.getMode().$id === 'ace/mode/html',
        'Ace HTML mode not initialized');
    check(controller.codeEditor.getValue().includes('\\n'), 'HTML source not pretty-printed');
    check(format.disabled && toggle.getAttribute('aria-pressed') === 'true', 'source controls');
    editor.dispatchEvent(new Event('mcms:content-sync'));
    check(body.value === initial, 'preview sync changed untouched HTML');
    check(pretty.hidden === false && pretty.disabled === false, 'format action not available');
    toggle.click();
    check(body.value === initial, 'visual round-trip changed untouched HTML');

    toggle.click();
    const unsafe = '<p data-test="1">Second</p><img src="broken" onerror="window.xss = 1">'
        + '<script>window.xss = 1</' + 'script>';
    body.value = unsafe;
    body.dispatchEvent(new Event('input', {bubbles:true}));
    editor.dispatchEvent(new Event('mcms:content-sync'));
    check(body.value === unsafe, 'source edit was overwritten');
    check(controller.codeEditor.getValue().includes('kept'), 'Ace source not shown');
    toggle.click();
    check(body.value === unsafe, 'source-to-visual switch serialized HTML');
    check(!editor.innerHTML.includes('onerror') && !editor.innerHTML.includes('<script'),
        'unsafe source reached visual DOM');
    editor.dispatchEvent(new Event('mcms:content-sync'));
    check(body.value === unsafe, 'autosave sync serialized unedited visual HTML');

    editor.innerHTML = '<p>Visual edit</p>';
    editor.dispatchEvent(new Event('input', {bubbles:true}));
    check(body.value.includes('Visual edit'), 'visual editing did not update canonical HTML');
    let toolbarAutosaves = 0;
    body.addEventListener('input', () => { toolbarAutosaves++; });
    editor.innerHTML = '<p>Toolbar edit</p>';
    controller.changed();
    check(body.value.includes('Toolbar edit'), 'toolbar changed visual HTML but did not sync');
    check(toolbarAutosaves >= 1, 'toolbar editing did not notify autosave');

    const restored = '<p class="old" data-raw="1">Restore</p>';
    editor.dispatchEvent(new CustomEvent('mcms:content-restore', {detail:{html:restored}}));
    check(body.value === restored, 'version recovery rewrote source');
    toggle.click();
    check(body.value === restored, 'recovered HTML changed on source switch');
    check(controller.codeEditor.getValue().includes('Restore'), 'restored source not shown in Ace');
    controller.codeEditor.setValue('<div><p>Changed from Ace</p></div>', -1);
    check(body.value.includes('Changed from Ace'), 'Ace source edits not synced into canonical HTML');
    editor.dispatchEvent(new Event('mcms:content-sync'));
    check(body.value.includes('Changed from Ace'), 'preview sync clobbered Ace source edits');
    check(format.disabled && toggle.getAttribute('aria-pressed') === 'true', 'source state after restore');
    toggle.click();
    controller.engine.setHTML('<p>format tests</p>');
    controller.engine.moveCursorToStart();
    const selection = document.createRange();
    selection.selectNodeContents(editor.querySelector('p'));
    controller.engine.setSelection(selection);
    controller.format('bold');
    check(/<(?:b|strong)>/i.test(editor.innerHTML), 'bold command did not format selection');
    controller.formatBlock('h2');
    check(editor.querySelector('h2') !== null, 'heading command did not create h2');
    controller.formatBlock('p');
    check(editor.querySelector('p') !== null, 'paragraph command did not restore p');
    controller.format('insertUnorderedList');
    check(editor.querySelector('ul') !== null, 'unordered list command');
    controller.engine.setHTML('<p>Media and tables</p>');
    controller.engine.moveCursorToEnd();
    controller.insertImage({url:'https://example.test/photo.jpg',alt_text:'test photograph'});
    check(editor.querySelector('img[alt="test photograph"]') !== null, 'image picker insertion');
    controller.engine.moveCursorToEnd();
    controller.insertTable(2, 3);
    check(editor.querySelectorAll('table td').length === 6, 'insert a 2x3 HTML table');
    controller.engine.setHTML('<p>Table dialog selection test</p>');
    controller.engine.moveCursorToEnd();
    document.getElementById('table-trigger').click();
    const tableDialog = document.getElementById('mcms-insert-table-dialog');
    check(tableDialog.open, 'table insertion dialog did not open');
    const tableForm = tableDialog.querySelector('form');
    tableForm.elements.namedItem('rows').value = '2';
    tableForm.elements.namedItem('columns').value = '2';
    tableForm.requestSubmit();
    check(!tableDialog.open && editor.querySelectorAll('table td').length === 4,
        'dialog did not insert 2x2 table');
    check(body.value.includes('<table'), 'table absent from canonical source');
    controller.engine.setHTML('<p>Click the link</p>');
    const linkSelection = document.createRange();
    linkSelection.selectNodeContents(editor.querySelector('p'));
    controller.engine.setSelection(linkSelection);
    controller.link('https://example.test/link');
    check(editor.querySelector('a[href="https://example.test/link"]') !== null, 'create link');
    controller.engine.setHTML('<p>Safe</p>');
    controller.engine.moveCursorToEnd();
    controller.link('javascript:alert(1)');
    check(!editor.querySelector('a[href^="javascript:"]'), 'dangerous link rejected');
    const gallery = document.createTextNode('[gallery id="17"]');
    editor.appendChild(gallery);
    editor.dispatchEvent(new Event('input', {bubbles:true}));
    check(body.value.includes('[gallery id="17"]'), 'gallery DOM insertion not synced');
} catch (error) {
    errors.push(String(error.stack || error));
}
setTimeout(() => {
    check(!window.xss, 'HTML source executed JavaScript');
    document.getElementById('result').textContent =
        errors.length ? 'FAIL: ' + errors.join('; ') : 'PASS';
}, 200);
</script>
'''
    document = (
        '<!doctype html><html><head><meta charset="utf-8">'
        '<style>.d-none{display:none!important}</style></head><body>'
        '<div id="container"><div id="toolbar">'
        '<button type="button" id="toggle" data-html-source-toggle>HTML</button>'
        '<button type="button" id="format">Format</button>'
        '<button type="button" id="pretty" data-html-format hidden>Pretty</button>'
        '<button type="button" id="table-trigger" data-insert-table>Table</button></div>'
        '<div id="visual" contenteditable="true">' + source + '</div>'
        '<textarea id="source" class="d-none">' + html.escape(source) + '</textarea>'
        '<div id="code" data-html-code class="mcms-html-code d-none" style="height:500px"></div>'
        '<p data-html-source-hint hidden>HTML source hint</p></div>'
        '<pre id="result">RUNNING</pre>' + widget + assertions + '</body></html>'
    )
    with tempfile.TemporaryDirectory(prefix="mcms-html-editor-") as temp:
        fixture = pathlib.Path(temp) / "roundtrip.html"
        fixture.write_text(document, encoding="utf-8")
        run = subprocess.run([
            chrome, "--headless", "--no-sandbox", "--disable-gpu",
            "--disable-dev-shm-usage", "--virtual-time-budget=1000",
            "--dump-dom", fixture.as_uri(),
        ], capture_output=True, text=True, timeout=30, check=True)
    result = re.search(r'<pre id="result">([^<]*)</pre>', run.stdout)
    if not result or result.group(1) != "PASS":
        raise SystemExit("HTML editor browser smoke failed: "
                         + (result.group(1) if result else run.stdout[-1200:]))
    print("PASS: source/visual round trip, safe DOM, preview/autosave sync, revision restore")


if __name__ == "__main__":
    main()
