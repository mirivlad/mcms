# mCMS

mCMS is a modular, self-hosted CMS platform built on CodeIgniter 4.

The Core is intentionally small: framework runtime, authentication/authorization,
administration, settings, module lifecycle, hooks, storage contracts and system
maintenance. Content and business features are installed as modules.

## Core stack

- PHP 8.4 target;
- CodeIgniter 4.7.4;
- CodeIgniter Shield 1.4.x;
- CodeIgniter Settings 2.4.x;
- MariaDB 11.4;
- nginx + PHP-FPM;
- Bootstrap 5.3.x for the default public UI;
- Tabler 1.6.x for the administration shell;
- Docker Compose / Portainer deployment.

## Core responsibilities

- login, registration, recovery and authorization;
- users, groups and permissions;
- admin dashboard and extension registry;
- module discovery, dependencies, enable/disable/update lifecycle;
- namespaced module migrations;
- persisted Core/module settings;
- stable events/hooks contract;
- storage abstraction;
- installation and first-superadmin bootstrap;
- health and diagnostics.

## Modules

Media, Pages, News, Menus, Galleries, Shop, Orders, Delivery and other domain
features are not part of Core.

A module owns its schema, settings, permissions, routes and admin contributions.
Disabling a module preserves its data.

See [docs/MODULES.md](docs/MODULES.md) and [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Development status

mCMS is in early Core bootstrap. The current work lives in
`bootstrap/generalize` until the modular Core contract and clean-install flow are
stable.

Ferma is used only as a functional reference for features that proved useful in a
real application. mCMS does not inherit Ferma's Git history, database schema,
customer data, routes or deployment assumptions.
