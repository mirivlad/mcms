# Menus module

Menus owns reusable navigation trees. It deliberately does not know which theme is
active and it does not define locations such as "header" or "footer".

A future Theme API will declare the menu locations supported by the active theme
and persist assignments from those locations to menu instances.

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

The schema contains a `target_type` plus an opaque `target` value so future
modules can add provider-backed navigation targets without changing the tree
model. Version 0.1 exposes only the `custom` target type in the administration
UI. It accepts local paths, anchors, queries and http(s)/mailto/tel URLs.

Menus does not depend on Pages. A Pages/News resource picker can be added later
through a generic target-provider contract rather than direct table access.

## Administration

The editor supports nested items, enabled/disabled state, opening links in a new
window and drag-and-drop ordering on desktop. The parent selector remains
available as a non-drag alternative and for mobile administration.

Disabling the module preserves both menus and items.