# mCMS architecture

mCMS is a modular, self-hosted CMS platform built on CodeIgniter 4.

The original Ferma/CI4 application is not an upstream dependency. It is used only as a catalogue of proven real-world functionality that may later be reimplemented as independent mCMS modules.

## Core boundary

The Core must remain useful without any content or commerce modules installed.

Core contains:

- CodeIgniter 4 runtime;
- CodeIgniter Shield authentication and authorization;
- CodeIgniter Settings persistence;
- initial setup and first-superadmin workflow;
- admin shell and dashboard extension points;
- module discovery, dependencies and lifecycle;
- module-scoped migrations;
- core users/roles/permissions administration;
- core settings UI;
- event/hook conventions based on CodeIgniter Events;
- storage abstraction for files owned by Core or modules;
- localization infrastructure;
- health, diagnostics and update infrastructure.

Core does not contain:

- media library;
- pages;
- news;
- menus;
- galleries;
- comments;
- search;
- shop/catalogue;
- cart;
- orders;
- stock;
- delivery;
- SEO;
- analytics;
- backups;
- third-party integrations.

Those belong in modules.

## Module lifecycle

A module is code on disk plus a `module.json` manifest and a provider implementing
`MCMS\Core\Modules\ModuleProviderInterface`.

Enabling a module:

1. resolves and enables required dependencies;
2. applies migrations only for the module namespace;
3. writes the module's default settings to the Settings database;
4. registers module permissions with Shield;
5. marks the module enabled;
6. allows its provider to register hooks, admin contributions and routes.

Disabling a module:

- stops routes, hooks and admin contributions from being registered;
- preserves tables, settings and user data.

Uninstall/data removal will be a separate explicit operation. Disable must never roll back schema.

Core startup deliberately does not use `php spark migrate --all`. This prevents code present on disk from silently creating tables when its module has not been enabled.

## Module isolation

mCMS module code is PSR-4 loadable, but CodeIgniter auto-discovery is restricted to approved Core packages.
mCMS modules must not rely on CI4 automatic `Config/Routes.php`, Events or Services discovery.

Instead a module provider explicitly registers everything through Core:

- `boot()` for event listeners and admin contributions;
- `routes()` for HTTP routes;
- namespaced migrations for schema;
- manifest settings and permissions for install-time registration.

This makes the database-backed enabled state authoritative.

## Admin UI

The administration shell is owned by Core.

Modules may register:

- sidebar/menu items;
- dashboard widgets;
- settings sections;
- permissions.

Modules must not replace or patch Core admin templates.

The current admin UI foundation is Tabler 1.6.1. The public/default theme targets Bootstrap 5.3.8.
Both are bundled into the nginx runtime image from exact npm package versions. mCMS Core does not bundle Tabler's demo/vendor libraries.

## Settings

Core uses CodeIgniter Settings as the persistence layer.

Core settings use Core-owned groups. Module defaults are written only on first installation and are then user-owned database values.

A module must never require editing a Core config file just to change an ordinary runtime option.

## Events / hooks

mCMS uses CodeIgniter Events rather than a second event bus.

Stable public event names use the `mcms.*` prefix, for example:

- `mcms.user.created`
- `mcms.module.enabled`
- `mcms.module.disabled`
- `mcms.settings.changed`

Modules define their own namespaced events for domain-specific behavior.

## Storage

Core exposes a small private storage abstraction: put/get/delete/exists.

Public URLs, media browsing, image metadata, thumbnails, folders, pickers and galleries are not Core responsibilities. They belong to a Media module.

## Database rule

A fresh mCMS installation starts from an empty database.

- Core tables are created by App/Core migrations.
- Shield owns authentication tables.
- Settings owns the settings table.
- Each module owns its tables in its namespace.

No client database dump is ever part of mCMS.
