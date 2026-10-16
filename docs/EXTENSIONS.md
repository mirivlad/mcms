# External package lifecycle

Core owns installation, updates, activation and removal. ZIP packages are trusted
PHP code, not a sandbox. Built-in modules/themes cannot be removed.

## Removal and reinstallation

Use the Remove action in the extension catalog, Modules or Themes. The confirmation
page lists code, owned tables, settings, scoped files and rollback history. Choose
the removal mode and check the backup acknowledgement; typing the package ID is
not required. Both modes require backup acknowledgement in the administration UI.

- **Keep data** disables a module, removes code and keeps its registry version,
  schema version, settings, scoped files, migration history and update snapshots.
  The exact same package version can be installed again. It remains disabled;
  enable it explicitly. Existing migration files must match their retained SHA-256
  hashes; migration history must also match. Newer versions must preserve these
  migrations. Older versions below the retained schema baseline are rejected.
- **Complete removal** also removes explicitly owned tables, module-scoped settings,
  permissions, files, registry/migration records and update history. It requires
  explicit confirmation of a current full DB/files backup kept outside the app
  volume, as with the existing module-update backup confirmation. Core cannot
  verify an operator's external backup. A later installation is clean.

Any discovered dependent module blocks **both** modes, even when disabled or only
its code is installed. There is no cascade. Active themes must first be switched.
Unfinished updates/restores block removal. Keep-data theme removal preserves
per-theme menu assignments for reinstallation; missing themes cannot be activated.
Complete removal deletes only that theme's assignments, never shared Menus/Media.

Removal takes the existing runtime writer lock, drains requests and saves an
atomic private journal before changing state. The module registry is disabled
before code is quarantined. Startup resumes interrupted operations forward before
booting packages. Errors keep maintenance active; fix DB/storage access and open
`/admin/extensions/recovery`. No arbitrary cleanup PHP or migration `down()` runs.
Supported deployment is one application instance with local filesystem locking,
as for module/theme updates. Back up both extension volumes with the database.

## Module cleanup contract

A publisher declares all owned tables, in safe drop order, in `module.json`:

```json
"cleanup": {"schema": 1, "complete": true, "tables": ["example_items"]}
```

`complete=true` is the author's reviewed assertion that all persistent resources
are those tables, Settings keys under `<module-id>.*`, declared namespaced
permissions and files in `modules-data/data/<module-id>`. A module writing shared
records, changing shared tables or keeping files elsewhere must **omit** this
contract until a safe explicit cleanup contract supports its resources. Core does
not guess ownership from table prefixes and does not run arbitrary cleanup hooks.

Core records tables actually created during namespace migrations in private
`modules-data/ownership/<id>.json`. Declaring an existing table does not grant
ownership. Complete deletion is refused when the inventory is incomplete,
undeclared tables exist in it, a migration changed shared table schemas, or foreign keys from other tables reference owned
tables. Legacy external modules without an ownership receipt can always keep
supported data; complete removal of unproven resources is refused.

Packages are trusted executable code: inventory cannot detect undeclared writes
made by arbitrary PHP to shared records/external services. Review the contract
and migrations before admitting a package. A migration interrupted before its
ownership receipt is completed deliberately leaves resources unproven and prevents
complete deletion; retain data and recover using a verified backup.

# GitHub extension catalog (schema 1)

The sole publication source is `mirivlad/mcms` on GitHub. No separate catalog
server, database, author registration or moderation website is required.
`catalog/modules/<id>/catalog.json` and `catalog/themes/<id>/catalog.json` are
the authoritative per-package metadata. `catalog/index.json` is their generated,
bounded (1 MiB / 200 extensions) aggregate, including available releases but no
archive contents. Its metadata is intentionally self-contained: a single daily
request supplies offline lists, details, versions and changelogs.

A minimal entry (replace example size/hash with the actual ZIP values):

```json
{
  "id": "example-module",
  "type": "module",
  "name": "Example module",
  "description": "An independent module.",
  "author": "Example author",
  "license": "MIT",
  "version": "1.0.0",
  "project": "https://github.com/example/example-module",
  "tags": ["demo"],
  "screenshots": [
    "https://raw.githubusercontent.com/mirivlad/mcms/main/catalog/modules/example-module/screenshots/preview.png"
  ],
  "versions": [{
    "version": "1.0.0",
    "mcms": "^0.1",
    "requires": [],
    "download": "https://raw.githubusercontent.com/mirivlad/mcms/main/catalog/modules/example-module/example-module-1.0.0.zip",
    "bytes": 1234,
    "sha256": "<actual lowercase 64-character SHA-256>",
    "changelog": "Initial publication."
  }]
}
```

The index wraps these entries in `{"schema":1,"extensions":[...]}`. IDs are unique
across types. `version` names the latest published release; each `versions` entry
has its own Core/dependency requirements, immutable download, exact bytes/hash
and plain-text changelog. Module dependencies use the same list as `module.json`;
themes use `[]`. Metadata is separate from manifests inside ZIPs. At download,
Core checks ID, version, Core constraint and dependency-list equality against the
actual manifest, in addition to normal ZIP and compatibility checks.

Archives may instead use a release asset of **this same repository**, e.g.
`https://github.com/mirivlad/mcms/releases/download/packages-v1/example-module-1.0.0.zip`.
This avoids large binaries in Git history; it does not change the installer.
Project/source links can point to another HTTPS site; the server does not fetch
them. Screenshots stay in the extension's own `catalog/.../screenshots/` folder.
The current catalog includes Atelier 1.1.1/1.1.2 and Lifecycle demo 1.0.0.

Catalog cards show the author, license, categories, installed/available versions
and compatible Core/module requirements. Details, install, update and removal
actions are available directly in the list when applicable. Install/update still
opens the standard package review; an enabled module must be disabled before its
update can be prepared.

## Publication through a pull request

1. Build a clean ZIP with `mcms:module:pack` or `mcms:theme:pack` and review its
   source, migrations, license and complete-cleanup declaration when present.
2. Add/update the extension folder, screenshots and `catalog.json`; retain old
   published releases needed for installation/rollback tests. Never silently
   replace an existing version with different code/assets.
3. Run `php spark mcms:catalog:validate --build`, then
   `php spark mcms:catalog:validate`. Validation checks JSON/schema, duplicate IDs,
   semantic versions/constraints, trusted download links, screenshot presence,
   archive size/SHA-256, ZIP integrity and manifest consistency. Raw catalog URLs
   resolve to local repository files; same-repository Releases assets are fetched
   with the production HTTPS restrictions. No Actions run is needed to iterate.
4. Submit the metadata, screenshots, archive (or already published immutable asset)
   and generated index in a GitHub PR. The project owner moderates code, package
   contents and cleanup ownership. CI runs the same validator before acceptance.
5. After approval/merge to `main`, installations receive the updated index during
   their next successful check. A package release asset is independent of an mCMS
   application release; publishing a new application release is unnecessary.

## Checks, cache and operations

Administration → Extension catalog has Themes/Modules tabs, search, category/state
filters, version history, requirements, screenshots and installation state.
Installed extension screens and the admin shell display cached update counts.
Permissions remain `core.modules` and `core.themes`; downloads and all mutations
check the appropriate permission. A theme-only manager cannot install modules.

Press Install/Update/Install with retained data to download and **preview** a ZIP.
The normal package page supplies the separate trust confirmation and applies it.
Module updates require the existing disable/prepare/full-backup/maintenance/fresh
worker flow; theme updates retain their existing safe swap and rollback behavior.
No version is installed automatically. Manual ZIP upload remains available.

The PHP cURL extension is needed for catalog networking (included in the standard
Docker image). Network sources are fixed HTTPS raw files on this repository's
`main/catalog` path, or this repository's Releases ZIP assets. Redirects are
refused except bounded Releases redirects to `release-assets.githubusercontent.com`.
Each hop pins validated public IPv4 DNS answers, verifies TLS, disables proxy
routing, and enforces connection/body/time/redirect limits. There are no tokens,
arbitrary URLs, local/private addresses or installer-side repository clones.

The private cache is in `modules_data/catalog/`. Atomic publication preserves the
last valid snapshot during outages, bad indexes, private-repository HTTP errors
and interrupted refreshes. The UI shows the last **successful** timestamp and the
last error separately. Ordinary admin rendering never performs a network request.
While an authorized admin uses the panel, a separate daily cache-reading request
can refresh stale metadata. It never changes packages or rotates CSRF tokens on
other open forms. A nonblocking refresh lock prevents concurrent downloads;
failures retry after one hour and explicit checks are limited to once per minute.

For scheduled checks, run inside the app container from cron/systemd, e.g.:

```sh
# Daily; adapt the Compose project/directory to your installation.
15 3 * * * cd /srv/mcms && docker compose exec -T app php spark mcms:catalog:refresh
```

Use `php spark mcms:catalog:refresh --force` for an explicit immediate check.
Without a scheduler, checks occur when the admin panel is used; inactive sites
cannot guarantee a wall-clock daily check. A public GitHub repository is required
for anonymous live access; a temporarily private repository produces a readable
error and preserves cached data/manual management. Authenticated/private-catalog
credentials and multi-instance distributed locks are outside this version.
