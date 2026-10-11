# Persistent file storage contract

This is a pre-release breaking reset (2026-10-11). Legacy development volumes
are **not** migrated, adopted or cleaned up automatically.

## Volumes

The website needs three data volumes; a fourth, separate system volume holds its backup archives:

- `db_data`: database
- `modules_data`: module code, installed archives and ALL module-owned files
- `themes_data`: installed theme code and theme-owned files
- `backup_data`: generated ZIP backups, deliberately excluded from site archives

The mail service additionally retains `mail_queue` and `dkim_data`.
Neither modules nor themes are allowed to require Compose changes or new volumes.

The application mounts both file volumes writable. Nginx mounts **only**
`modules_data` read-only; it may expose only the Media public subtree through
a tightly scoped alias, not the module ZIP code or any other module directory.
The public Media URL remains `/assets/uploads/media/YYYY/MM/<file>`.

```text
writable/modules-data/             (Docker volume: modules_data)
  installed/                      # external PHP modules
  pending/                        # ZIP staging
  updates/                        # update snapshots/recovery
  data/
    media/                        # Media uploads (incl. thumbnails)
    product-images/               # ProductImages originals + derivatives
    catalogue/category-icons/     # Catalogue icons
    <module-id>/                  # any installed module's private data
  core/                           # Core private objects

writable/themes-data/             (Docker volume: themes_data)
  installed/                      # external theme ZIP contents
  pending/                        # installation staging
  data/<theme-id>/                # optional theme-owned data
```

Database tables are separately migrated per module. The Core allocates storage
directories automatically and persistently, without a mount for each extension.

## Module contract

During `boot(ModuleContext $context)`, an enabled module receives
`$context->storage`, an instance of `MCMS\Core\Storage\ScopedStorage`.
A module writes files relative to its own namespace:

```php
$context->storage->put('reports/month.csv', $csv);
$csv = $context->storage->get('reports/month.csv');
```

An image-processing library that needs an absolute filesystem path can use
`$context->storage->directory('images')` (creates the directory) and
`$context->storage->path('images/photo.webp')`. Outside `boot()`, use
`service('mcmsModuleStorage', 'module-id')`. Never use hardcoded
`WRITEPATH` or `FCPATH` for persistent module files. The scoped API rejects
unsafe IDs and dot-traversal paths, and is intended for **trusted** extension
code; it is not a PHP sandbox. Do not make module files publicly accessible
just because they are in the same volume. Only files intended for public
serving may use `directory(public: true)` (used by Media); other directories
are private and nginx must not be able to traverse them.

A theme can use `service('mcmsThemeStorage', 'theme-id')` for optional
persistent data. Installed ZIPs live in a separate subtree from mutable data.

## Development reset

No migration or automatic deletion exists for the former
`media_data`, `product_images_data`, `module_packages_data` or
`theme_packages_data` volumes. The current test deployment contains only demo
content; recreate it from scratch when accepting the new layout. Never drop
volumes on an unrelated deployment. On a fresh Portainer Git stack, declare
`portainer-stack.yml` from main and let Docker create the common volumes.

Backup and restore must include the database and both CMS file volumes together.

Core backup archives are written to `backup_data`. No module gets its own
volume. See [Backup management](BACKUPS.md).
