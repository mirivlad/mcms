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
The IDs `package` and `images` are reserved for Core administration routes.
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

## Dynamic menu sources and conditional assignments

The mCMS 0.1.x Theme API keeps its original
`Core.themeMenuAssignments` contract: every theme location still accepts one
primary Menus menu. It additionally supports **up to two ordered extra menus
per location**, configured under Administration → Themes → Configure. Extras
are stored separately in `Core.themeExtraMenus`; switching themes preserves
each theme's assignments and existing theme ZIPs require no migration.

Each extra entry selects a source and visibility rule:

- `menu:<id>` refers to a normal Menus tree (it stays editable in Menus).
- `dynamic:catalogue.categories` follows live public Catalogue categories
  without copying category records into Menus.
- Visibility rules: all pages, shop pages, pages, news or product pages.
  Conditions are evaluated against the logical template while rendering,
  not against the visitor-controlled URL or query string.

Existing custom themes continue to receive the same `themeMenus` array by
location. The additional trees are appended to the old primary tree in
assignment order; `max_depth` from `theme.json` remains effective. If a
source module is disabled or removed, its assignment stays saved but produces
no public items, and returns when that provider is re-enabled.

A module can expose another generated source by implementing
`MCMS\Core\Themes\DynamicMenuSourceInterface` and registering its service
in the module runtime:

```php
$context->services->register('my-module.menu', new MyDynamicMenuSource());
```

A source has a stable, unique `id()`, a translated `label()` and a read-only
`tree()` of `label`, `url` and `children` values following the normal
Menus tree structure. It must return only public resources, and should not
depend on another optional module being enabled.

The Catalogue's separate `/admin/catalogue/settings` also lets a store use
a regular Menus tree as additional shop navigation, and controls the category
tree display (full, collapsible or horizontal).

## Consistent public content layout and editor shortcuts

All built-in themes and the installable Atelier example include Core's
`themes/content_assets` stylesheet in their layout and render
`themes/content_actions` and `themes/content_interactions` on Pages and
News articles. The shared components keep comment/rating widgets within the
same reading column as the article and expose non-destructive Edit / Manage
links for signed-in users with the corresponding module permission. The
administration endpoints independently recheck those permissions.

Catalogue product pages and public product cards use the same shortcuts.
News cards do too. A theme that implements its own public resource templates
should include these components itself and supply the stable resource ID.
Comments/Ratings remain optional and are never inserted into private previews.

`MCMS\Core\Content\PublicContent` suppresses an author-supplied leading H1
when it exactly repeats the material title, and an excerpt when it repeats the
first body paragraph. This happens only at render time; the editor/source text
is preserved.

The shared CSS also trims excessive content-page spacing while preserving
theme-specific color and typography. The packaged Atelier v1.1.2 uses the same
Core components; existing installed ZIP themes are not automatically replaced
by a Portainer redeploy.

## Storage and lifecycle

Preview archives are private in `writable/themes-data/pending`, expire after one hour,
and are bound to the uploading session/user. Installation checks SHA-256 again
and rechecks Core compatibility and ID/namespace collisions under a lock.
Files are staged and atomically published to `writable/themes-data/installed/<id>`.
An existing theme cannot be overwritten; package updates/removal are outside this
workflow. Activation checks Core compatibility independently.

Compose and Portainer persist the private directory in `themes_data`,
mounted only in the application. Include it in backups alongside the database
(which stores active selection
and per-theme settings). First-party themes remain in the application image.
External resources are served by Core at `theme-assets/<id>/<version>/<path>`
with explicit MIME types, nosniff, ETag and immutable caching. PHP, manifests and
views are never served through this endpoint; SVG responses disable scripts.
No nginx mount or exposure of the private theme directory is required.

## Example: installable Atelier editorial theme

The repository includes a full third-party example under
[`examples/themes/mcms-atelier`](../examples/themes/mcms-atelier/README.md),
with a ready-to-upload archive at
[`examples/packages/mcms-atelier-1.1.2.zip`](../examples/packages/mcms-atelier-1.1.2.zip).

Atelier illustrates how to support all current public templates, menu
locations, editable Home content, shared login/registration/account links,
Core account.* templates inside the same theme layout, local assets and
bilingual view labels (without a forbidden `Language/` directory) and accessible galleries. The archive is
intentionally **not** located in the first-party `themes/` tree: doing that
would prevent installing the ZIP due to duplicate identity checks.

To recreate the ZIP from source, run:

```sh
php spark mcms:theme:pack examples/themes/mcms-atelier /tmp/mcms-atelier-1.1.2.zip
```

The installer requires a separate trust-confirmation and activation step.

Per-theme persistent files, when needed, must use `service('mcmsThemeStorage', '<theme-id>')` within `themes_data/data/<theme-id>`; themes never require another Docker mount.
