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
`"data_providers": {"media.usage": "MCMS\\\\Modules\\\\Example\\\\Api\\\\MediaUsageProvider"}`.
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
            permission: 'example.manage'
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

First-party modules should ship their own `Language/<locale>/...` files and avoid
hard-coded UI strings. This keeps module translations inside the module instead of
growing Core's language catalogue.

## Public module services

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
