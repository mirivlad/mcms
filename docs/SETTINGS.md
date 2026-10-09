# Settings centre

Core 0.1.6 adds grouped settings navigation at `/admin/settings`. The original
module locations remain available. Central and original routes use the same
controllers, forms, permissions and stored values; saving through either entry
point updates the other. Central form actions and successful redirects remain
inside the selected section.

Groups are Site, Appearance, Content, Shop and Integrations. Only enabled modules
contribute sections, and each section is filtered by its own permission. Original
module routes retain their permission checks, as do the central aliases. Disabling
a module hides its section without deleting settings.

First-party sections include General, Themes, Media, Pages, SEO, Delivery and API.
Theme appearance/Home/menu forms, delivery editing and key creation/revocation are
usable through the centre as well as their existing screens. Integration with
News, Catalogue and Notifications is added in subsequent stages.

Modules register their entry point during `boot()`:

```php
$context->admin->addSettings('example', '@Example.settings', '/admin/example/settings',
    'ti ti-adjustments', 'example.settings', 'content');
```

Register `/admin/settings/example` as a session-protected alias to the **same**
settings controller. Keep `/admin/example/settings`. The module still owns field
validation and persistence. Core does not inspect arbitrary module settings or
turn configuration keys into editable controls automatically.

`BaseAdminController::settingsPath()` preserves the current central section for
redirects. Admin views receive `$settingsUrl`, which produces the appropriate
URL for a canonical module path. Other module paths are not rewritten. Sections
use groups `general`, `appearance`, `content`, `commerce`, or `integrations`.
