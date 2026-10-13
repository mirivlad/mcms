# mCMS Classic HTML Editor

The Pages and News modules share the classic editor engine in
`app/Views/admin/content/html_source.php`. Rich-text operations are provided by
[Squire](https://github.com/fastmail/Squire) (MIT), pinned to upstream commit
`0ac85079672d056f33ceb76a355515bebe8f807c` (2026-10-07).
The local bundle is `public/assets/vendor/squire/squire.min.js` and its license
is `public/assets/vendor/squire/LICENSE`. No CDN or runtime Node/npm dependency.

## Content contract

* The hidden HTML textarea is the **canonical submitted form field**.
* Opening the HTML source view never serializes the browser DOM; returning to
  visual view parses a safe representation while preserving the textarea's exact
  HTML bytes **until the user modifies the visual document**.
* Once the visual document is edited, Squire's `getHTML()` becomes the submitted
  value. As with any WYSIWYG engine, this can normalize HTML. If exact hand-written
  markup must be retained, stay in HTML source mode.
* The shared source controller raises a form `input` event so auto-drafts observe
  toolbar, table, link and image insertions. The preview and draft workflow use
  `mcms:content-sync`; revision restoration uses `mcms:content-restore`.
* A detached-template sanitizer protects the admin's visual DOM, **not the saved
  output**. On save/preview, `MCMS\\Core\\Content\\HtmlSanitizer` enforces the server
  allowlist. Raw scripts and arbitrary attributes are intentionally unsupported.
* The Galleries picker injects shortcode text and emits `input`. The Media
  pickers use the editor's `insertImage` method so changes are tracked by Squire.

## UI

Classic HTML editing with undo/redo, bold/italic, paragraph/heading,
numbered/bulleted lists, quotes, link, image picker, gallery picker and a
rows/columns table dialog. The source toggle is shared between both modules.

## Verification

* `python3 .github/scripts/html-source-editor-smoke.py` runs the actual Squire
  bundle and controller in headless Chrome/Chromium. It covers source round trips,
  unsafe markup, formatting, images, shortcode insertion, tables, links, preview
  sync, autosaving and revision restoration.
* `composer test` and GitHub clean-install CI remain part of routine checks.

The initial upstream parser is a rendering convenience and is not a substitute
for output validation. Keep any future editor extensions aligned with the
server-side sanitizer and explicit HTML storage contract.
