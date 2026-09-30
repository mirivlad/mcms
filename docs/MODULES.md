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
        $context->admin->addMenu(
            'example',
            'Example',
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

## Rules

- A module owns its schema and migrations.
- A module may depend on another module, but may not reach into its private tables/classes without a documented contract.
- Disable preserves data.
- Core never automatically migrates every namespace.
- Routes/hooks are registered only while enabled.
- Module runtime settings live in the Settings database.
- Module permissions must use the module ID as their namespace (for example `example.manage`). They are contributed to Shield at install/update time. Superadmins receive every module permission automatically; a module may optionally declare default grants for other existing Core groups. Grants may only reference permissions declared by that module.
- A module must not patch Core templates or Core source files.
