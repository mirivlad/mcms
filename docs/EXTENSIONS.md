# External package lifecycle

Core owns installation, updates, activation and removal. ZIP packages are trusted
PHP code, not a sandbox. Built-in modules/themes cannot be removed.

## Removal and reinstallation

Use the Remove action in Modules or Themes. The confirmation page lists code,
owned tables, settings, scoped files and rollback history. Type the package ID.

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
undeclared tables exist in it, or foreign keys from other tables reference owned
tables. Legacy external modules without an ownership receipt can always keep
supported data; complete removal of unproven resources is refused.

Packages are trusted executable code: inventory cannot detect undeclared writes
made by arbitrary PHP to shared records/external services. Review the contract
and migrations before admitting a package. A migration interrupted before its
ownership receipt is saved deliberately leaves resources unproven and prevents
complete deletion; retain data and recover using a verified backup.
