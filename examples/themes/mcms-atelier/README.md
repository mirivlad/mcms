# mCMS Atelier — editorial sample theme

**Atelier 01** is a self-contained *installable third-party ZIP theme*, not a built-in
theme. It demonstrates the external theme API using the same public module data
as the bundled themes.

## Design

An editorial portfolio/magazine layout: parchment background, deep green ink,
terracotta highlights, oversized serif headings, high-contrast navigation,
geometric SVG cover art and asymmetrical cards. No trackers, external fonts,
network requests, JavaScript frameworks or third-party assets.

The theme includes page, news, gallery, gallery embed, contact, search,
catalogue and cart templates. Navigation supports assigned main/footer/legal
menus and falls back to enabled public sections when none are assigned. It
honours the site logo, favicon and contact details, and provides editable Home
text, optional hero image, buttons, news and gallery sections.

Presentation strings use `Views/Text.php` for Russian and English because mCMS
intentionally **disallows `Language/` inside external ZIP themes**. `theme.json`
uses plain-text Russian defaults; edit the Home fields in the administration
interface to customise them. Site content is always rendered from the CMS.

## Installation (do not place under the built-in `themes/` directory)

1. Download `examples/packages/mcms-atelier-1.0.0.zip` from this repository.
2. Administration → Themes → **Install theme ZIP**.
3. Upload and inspect the preview; confirm trust in the PHP templates.
4. Install (initially inactive), then **activate Atelier** separately.
5. Optionally edit Home content, logo, menus and appearance in Themes.

An existing ZIP theme ID cannot currently be replaced by installing a second
archive with the same ID. Use a fresh ID/namespace when forking, or follow the
supported lifecycle in `docs/THEMES.md`.

## Build your own ZIP

From the mCMS repository root:

    php spark mcms:theme:pack examples/themes/mcms-atelier /tmp/mcms-atelier-1.0.0.zip

`theme.json` must be **at the root of the ZIP**, not inside a parent folder.
The official packer validates archive paths, file types, sizes and references.
Keep this source outside the repository's first-party `themes/` directory to
avoid a built-in/external theme identity collision.

## Customisation

- `theme.json`: package identity, Home settings and menu locations
- `Views/layout.php`: accessible shared layout
- `Views/home.php`: editorial landing page and dynamic cards
- `Views/Text.php`: RU/EN presentation labels
- `assets/css/theme.css`: layout and visual system; includes a proven responsive
  baseline adapted from the project's bundled Focus theme
- `assets/images/hero.svg`: default hero art (overridable in Home settings)
- `assets/screenshot.svg`: lightweight theme preview
- `assets/js/theme.js`: progressive enhancement for gallery dialog

This is trusted PHP presentation code, not a sandboxed template. Review it
before installing it on a production site.

## Licensing

Example theme code and geometric illustrations: MIT (`LICENSE.txt`).
The responsive view/CSS baseline was adapted from mCMS Focus in the same
repository. No external images or fonts are redistributed.
