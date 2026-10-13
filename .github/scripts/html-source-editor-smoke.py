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
    if not widget.startswith('<script src=') or not widget.rstrip().endswith("</script>"):
        raise SystemExit("Expected production HTML editor view with local Squire")
    # PHP resolves base_url in production. In the browser fixture use file://.
    widget = '<script src="' + vendor.as_uri() + '"></script>\n' + widget.split("\n", 1)[1]

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
    const initial = body.value;
    const controller = window.mcmsCreateHtmlSourceEditor(editor, body, toolbar);
    document.execCommand = () => { throw Error('Deprecated execCommand called'); };

    toggle.click();
    check(body.value === initial, 'source-toggle changed initial HTML');
    check(editor.hidden && !body.classList.contains('d-none'), 'source layout');
    check(format.disabled && toggle.getAttribute('aria-pressed') === 'true', 'source controls');
    editor.dispatchEvent(new Event('mcms:content-sync'));
    check(body.value === initial, 'preview sync changed untouched HTML');
    toggle.click();
    check(body.value === initial, 'visual round-trip changed untouched HTML');

    toggle.click();
    const unsafe = '<p data-test="1">Second</p><img src="broken" onerror="window.xss = 1">'
        + '<script>window.xss = 1</' + 'script>';
    body.value = unsafe;
    body.dispatchEvent(new Event('input', {bubbles:true}));
    editor.dispatchEvent(new Event('mcms:content-sync'));
    check(body.value === unsafe, 'source edit was overwritten');
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
    check(toolbarAutosaves === 1, 'toolbar editing did not notify autosave');

    const restored = '<p class="old" data-raw="1">Restore</p>';
    editor.dispatchEvent(new CustomEvent('mcms:content-restore', {detail:{html:restored}}));
    check(body.value === restored, 'version recovery rewrote source');
    toggle.click();
    check(body.value === restored, 'recovered HTML changed on source switch');
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
        '<button type="button" id="format">Format</button></div>'
        '<div id="visual" contenteditable="true">' + source + '</div>'
        '<textarea id="source" class="d-none">' + html.escape(source) + '</textarea>'
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
