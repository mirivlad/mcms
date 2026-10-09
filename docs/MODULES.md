# Module contract

## Layout

A first-party module lives under `modules/<Name>/`:

```text
modules/
  Example/
    module.json
    Module.php
    Controllers/
    Models/
    Services/
    Views/
    Language/
    Database/
      Migrations/
      Seeds/
```

The namespace must match the path below `MCMS\Modules\`.

## Manifest

```json
{
  "id": "example",
  "name": "Example",
  "version": "1.0.0",
  "namespace": "MCMS\\Modules\\Example",
  "provider": "MCMS\\Modules\\Example\\Module",
  "description": "Example module.",
  "requires": [
    {"id": "media", "version": "^1.0"}
  ],
  "settings": {
    "enabled_feature": true,
    "items_per_page": 20
  },
  "permissions": {
    "example.view": "View Example content.",
    "example.manage": "Manage Example content."
  },
  "grants": {
    "admin": ["example.view"]
  }
}
```

Both the `mcms` compatibility constraint and dependency `version` constraints use Composer semantic-version syntax and are enforced before a module is enabled.

Modules with fixed public URL prefixes declare them in `public_paths`, for example
`"public_paths": ["news"]`. Pages reserves these prefixes for all discovered
modules, including disabled ones, so later enablement cannot shadow a new page.
Fixed module routes take precedence over Pages' catch-all route independently of
module boot order. The `core.public_paths` read-only provider reports legacy
conflicting page slugs. Core refuses enablement that would shadow stored content;
existing conflicting slugs must be renamed explicitly, without automatic data edits.

An installed module may declare a read-only `data_providers` map, for example
`"data_providers": {"media.usage": "MCMS\\Modules\\Example\\Api\\MediaUsageProvider"}`.
Core exposes those declarations through `installedDataProviders()` even when the
module is disabled. A consumer validates the provider against its public contract;
providers are constructed without arguments and may only inspect their own stored
data. This does not boot a disabled module or register its routes, hooks or admin UI.
Media uses this contract to discover references before deleting a shared file,
including references in drafts and disabled modules.

## Provider

```php
<?php

namespace MCMS\Modules\Example;

use CodeIgniter\Router\RouteCollection;
use MCMS\Core\Modules\ModuleContext;
use MCMS\Core\Modules\ModuleProviderInterface;

final class Module implements ModuleProviderInterface
{
    public function boot(ModuleContext $context): void
    {
        $context->services->register('example.api', new ExampleApi());

        $context->admin->addMenu(
            'example',
            '@Example.Admin.menuLabel',
            '/admin/example',
            permission: 'example.manage',
            group: 'content'
        );
    }

    public function routes(RouteCollection $routes): void
    {
        $routes->group('admin/example', ['filter' => 'session'], static function ($routes): void {
            $routes->get('/', '\\MCMS\\Modules\\Example\\Controllers\\Admin::index');
        });
    }
}
```

Module manifest `name`, `description`, permission descriptions, and admin menu
labels may begin with `@`. Such values are language keys and are resolved in the
active site locale after module discovery registers the module namespace.

Admin menu contributions choose one sidebar `group`: `content`, `appearance`,
`commerce` or `administration`. Contributions that omit the group (or use an
unknown group) appear under `extensions`, preserving compatibility with existing
modules. `main` is reserved for standalone links such as the Core dashboard.
Empty groups are hidden after filtering by user permissions; the current page's
group opens automatically, including on nested editor/settings routes.

First-party modules should ship their own `Language/<locale>/...` files and avoid
hard-coded UI strings. This keeps module translations inside the module instead of
growing Core's language catalogue.

## Public module services

An enabled module can register a `ShortcodeProviderInterface` implementation in
its public service registry. Core's `mcmsShortcodes` renderer expands structured
`[tag key="value"]` tokens in sanitized text nodes, excluding attributes, links
and code examples. Providers return trusted, escaped phrasing HTML so fragments
remain valid inside editor paragraphs. Rendering is non-recursive, caches repeated
tokens within one call and processes at most 50 tokens per body. Unsupported or
disabled structured tokens render nothing; ordinary bracketed text is preserved.
Pages and News expand bodies only for public output, leaving saved source intact.

A module that exposes functionality to other modules should register a public API object through
`$context->services` during `boot()`.

Dependent modules retrieve that API from the same registry instead of reading another module's
private tables or constructing its internal classes directly.

Enabled modules are booted in dependency order. If `gallery` requires `media`, Media's provider
is booted first, so Gallery may safely consume a service such as `media.library`.

Service IDs are global runtime identifiers and should be namespaced with the owning module ID,
for example:

```text
media.library
media.images
shop.catalog
```

Registering the same service ID twice is an error.

## Rules

- A module owns its schema and migrations.
- A module may depend on another module, but may not reach into its private tables/classes. Cross-module runtime APIs are published through the module service registry.
- Disable preserves data.
- Core never automatically migrates every namespace.
- Routes/hooks are registered only while enabled.
- Module runtime settings live in the Settings database.
- Module permissions must use the module ID as their namespace (for example `example.manage`). They are contributed to Shield at install/update time. Superadmins receive every module permission automatically; a module may optionally declare default grants for other existing Core groups. Grants may only reference permissions declared by that module.
- A module must not patch Core templates or Core source files.

## ZIP package contract

`php spark mcms:module:pack modules/Example /tmp/example-1.0.0.zip` builds an
archive from a module directory, including a clean local repository checkout.
The archive contains `module.json` at its root, without an enclosing directory.
The command refuses to overwrite an existing output. Package metadata and files
are inspected without loading the provider or running migrations.

Packages use a unique `MCMS\\Modules\\Name` namespace, a provider inside that
namespace with a matching PHP file, a semantic release version and a non-Core id.
Only regular files and directories with relative ASCII paths are accepted;
links, traversal, hidden files, duplicate/case-conflicting paths, encryption and
ambiguous trailing dots/spaces are rejected. Limits: 20 MiB archive, 50 MiB total
content, 8 MiB per file, 2000 entries and 64 KiB manifest.

Packages cannot contain top-level `Config`, `Commands`, `vendor` or `node_modules`
directories. Routes and boot behavior must use the explicit provider lifecycle;
framework auto-discovery must not activate disabled packages. Dependencies use
Core's existing APIs; the installer does not run Composer, npm, shell scripts or
fetch arbitrary repositories. For a repository, export the desired revision and
package its module directory locally before uploading the ZIP.

External code is stored under `writable/modules/installed/<Name>`, separately
from first-party `modules/`. `ModuleManager` discovers both roots and registers
external namespaces with the CodeIgniter autoloader, including when Composer's
production class map is authoritative. Docker and Portainer preserve this root
in `module_packages_data`; include it in backups alongside the database. Pending
archives are private, expire after one hour and are not discoverable modules.

Publishing uses an installation lock and an atomic directory rename on the same
filesystem. Compatibility, dependency cycles, ids, namespaces and declared public
paths are checked again immediately before publishing. Adding code does not
create a registry row, run a migration, boot a provider or enable dependencies;
explicit enablement first checks that the provider implements the Core contract
and can be constructed without arguments, then uses the existing lifecycle and
public-content conflict check. Invalid providers cannot set the enabled flag.
The installation form refuses existing ids/directories; use the dedicated update
workflow below. Packages are executable trusted code, not a sandbox;
administrators must review their source before installation or update.

## Updating an installed ZIP module

In Administration → Modules, use the refresh icon on an external module. This
workflow only replaces installed ZIP packages under `writable/modules/installed`.
Built-in modules continue to ship with the application image.

1. Disable the target module and every enabled module depending on it. The target
   must already have a registry row with matching code/schema versions.
2. Upload a newer ZIP with exactly the same id, namespace and provider. Preview
   checks Core and forward/reverse dependency constraints, including disabled
   dependents, namespace/public-path conflicts and the existing ZIP safety limits.
   Existing migration PHP files must be retained byte-for-byte; add new migrations
   instead of editing/removing old ones. This is checked again when preparing.
3. Confirm that you trust the source. Preparation extracts a private candidate,
   fingerprints both trees and saves a downloadable `previous.zip`. It does not
   execute package PHP or change installed code/database state. The prepared
   update can be cancelled; enabling the target is blocked until it is resolved.
4. Make a **full database backup after preparation**, keep it outside the app
   volume, and confirm that it exists. Enable any required dependencies first;
   take the backup after doing so. Apply rechecks code, schema, checksums and
   dependency compatibility before entering persistent maintenance.
5. The runtime lock drains normal requests before code replacement. A fresh PHP
   CLI worker publishes the candidate and runs only the target namespace's new
   migrations and permission registration. The target remains disabled. FPM
   invalidates affected PHP cache entries before normal traffic resumes. Review
   the result, then enable the module separately.

The supported Compose/Portainer deployment has a single application instance and
local filesystem locking. Multiple independent app instances/rolling updates are
not covered by this workflow. PHP CLI and `proc_open` must be available to FPM;
if a worker cannot launch or is interrupted, maintenance stays active.

### Recovery after an error or interruption

MariaDB DDL is not a transaction-wide rollback. Restoring PHP cannot undo schema
or data changes, and mCMS does **not** create/restore a database backup or run down
migrations. A failed update keeps public/business requests at HTTP 503; Core
login, health and module recovery pages remain reachable. The maintenance page
links to module recovery, which requires `core.modules`. Startup updater and demo
initialization skip writes during recovery, so recreating the app container does
not apply more migrations or prevent FPM from starting.

Wait for any running worker to finish before touching the database. Restore the
**full pre-update database backup outside mCMS**, then confirm restoration on the
module recovery page and choose Restore previous code. Core requires the target
to be disabled and checks its registry version and migration history against the
saved baseline. These checks cannot verify all restored data; the operator must
verify the full backup restoration. Recovery restores the previous ZIP without
loading the target module or migrating down, then clears maintenance after FPM
cache invalidation. The module remains disabled for inspection.

Receipts, candidates, logs, previous code and the maintenance marker are private
under `writable/modules` on `module_packages_data`. A new update retains the prior
completed receipt/snapshot under `history/`; include this volume in backups and
manage retained history as part of normal storage maintenance. Previous-code ZIP
downloads require `core.modules`. A successfully applied update has no one-click
code rollback against the migrated database.

`mcms:module:update <id> <operation-token> apply|restore` is the internal fresh
worker command. It requires an existing matching receipt and maintenance owner;
it cannot start an arbitrary unprepared module update.
