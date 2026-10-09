# Theme packages

A theme is trusted PHP presentation code. Core owns discovery, logical template
selection, menu assignments, appearance and Home settings. Installation never
activates a theme or runs its views. An administrator with `core.themes` reviews
the archive and explicitly confirms trust before installation, then activates it
from Themes. Keep a known working theme available for switching back.

## Package format

Use a clean source directory (export a repository with `git archive`; do not
include `.git`, dependencies or build tooling). `theme.json` must be at the ZIP
root, with a unique lowercase/hyphenated `id`, semantic `version`, unique
`MCMS\Themes\Name` namespace and explicit Composer-compatible `mcms` constraint.
For example:

```json
{
  "id": "studio",
  "name": "Studio",
  "version": "1.0.0",
  "namespace": "MCMS\\Themes\\Studio",
  "mcms": "^0.1",
  "templates": {"home": "home/index", "page": "pages/show"},
  "screenshot": "preview.svg",
  "menu_locations": {"header.primary": {"label": "Main menu", "max_depth": 2}},
  "home_defaults": {"title": "Welcome"}
}
```

`home` is required. Every declared template must exist as
`Views/<mapped-path>.php`; other templates fall back to Core/module views.
Views may include layouts/partials under `Views/` using their declared namespace.
The existing first-party themes demonstrate the view data contract (`themeAssets`,
`themeMenus`, `themeAppearance`, `themeHome` and module-provided page data).
Package labels/default text are plain strings, without `@` language lookups.
Optional `home_defaults` makes Home editing available in theme settings.

Allowed files are `theme.json`, PHP under `Views/`, static files under `assets/`,
and optional `README.md`, `LICENSE`, `LICENSE.md` or `LICENSE.txt`.
Resources may use lowercase css/js/png/jpg/jpeg/gif/webp/avif/svg/ico/woff/woff2/ttf/otf
extensions. `screenshot` is an image path relative to `assets/`.
Config, Commands, Language, providers and dependency directories are forbidden.
Archive paths must be relative ASCII paths without hidden/dot segments, links,
special files, conflicting case, duplicates or encrypted entries.

Limits: 20 MiB ZIP, 50 MiB unpacked, 8 MiB per file, 2000 entries, 64 KiB manifest.
Build and validate locally:

```sh
php spark mcms:theme:pack /path/to/export /tmp/studio.zip
```

The output must not already exist. The command validates bytes and metadata;
it does not execute or certify the PHP template code.

## Storage and lifecycle

Preview archives are private in `writable/themes/pending`, expire after one hour,
and are bound to the uploading session/user. Installation checks SHA-256 again
and rechecks Core compatibility and ID/namespace collisions under a lock.
Files are staged and atomically published to `writable/themes/installed/<id>`.
An existing theme cannot be overwritten; package updates/removal are outside this
workflow. Activation checks Core compatibility independently.

Compose and Portainer persist the private directory in `theme_packages_data`,
mounted only in the application. Add this mount when upgrading an existing stack
and include it in backups alongside the database (which stores active selection
and per-theme settings). First-party themes remain in the application image.
External resources are served by Core at `theme-assets/<id>/<version>/<path>`
with explicit MIME types, nosniff, ETag and immutable caching. PHP, manifests and
views are never served through this endpoint; SVG responses disable scripts.
No nginx mount or exposure of the private theme directory is required.
