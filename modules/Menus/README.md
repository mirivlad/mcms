# Menus module

Menus owns reusable navigation trees. It deliberately does not know which theme is
active and it does not define locations such as "header" or "footer".

The Core Theme API declares the locations supported by each theme and persists
assignments from those locations to menu instances. Menus implements Core's
`MCMS\Core\Themes\MenuSourceInterface`, so ThemeManager can consume navigation
without knowing Menus implementation classes. Menus itself remains completely
independent of the active theme and of location names such as `header.primary`.

## Public service

When enabled, Menus registers:

`menus.repository` → `MCMS\Modules\Menus\Api\MenuRepositoryInterface`

The service can:

- create and manage named menu instances;
- find a menu by its stable handle;
- manage nested menu items;
- return an ordered tree for administration or rendering;
- return enabled-only trees for public rendering.

## Item targets

The schema contains a `target_type` plus an opaque `target` value. Custom
links use `target_type=custom` and accept local paths, anchors, queries and
http(s)/mailto/tel URLs.

Internal links use a provider ID as `target_type` and the provider's stable
resource ID as `target`. Providers implement Core's
`MCMS\Core\Navigation\NavigableResourceProviderInterface` and register with
`NavigableResourceRegistry`. Menus never reads another module's tables and
does not know provider-specific classes.

Pages currently registers `pages.page`; future modules such as News can add
their own providers without changing Menus. If a provider is disabled or the
resource disappears, the stored target is preserved and renders as unavailable
instead of being converted into a stale URL. Re-enabling the provider restores
resolution automatically.

## Administration

The editor supports nested items, enabled/disabled state, opening links in a new
window and drag-and-drop ordering on desktop. The parent selector remains
available as a non-drag alternative and for mobile administration.

Disabling the module preserves both menus and items.