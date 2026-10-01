# Current baseline — 2026-10-02

This document records the current known-good mCMS implementation checkpoint.

## Core

- CodeIgniter 4.7.4, Shield 1.4.x and Settings 2.4.x.
- Database-backed module discovery, dependency resolution and lifecycle.
- Namespace-scoped module migrations; Core never runs `migrate --all`.
- Public module service registry and administration contribution registry.
- First-run browser installer plus CLI recovery bootstrap.
- Users, groups, permissions, Core settings and system diagnostics.
- English/Russian localization.
- Tabler 1.6.1 administration shell with responsive navigation.
- HTTPS reverse-proxy support and standard CodeIgniter error views.
- Theme discovery, manifest validation, activation and logical template rendering.
- Per-theme menu-location assignments through the Core navigation source contract.

## First-party modules

### Media 0.1.0

- Persistent media library with upload and metadata editing.
- Configurable upload/image limits and MIME allow-list.
- Public service: `media.library`.

### Pages 0.1.0

- Draft/published static pages with sanitized rich text.
- Slug generation, SEO fields and Media picker integration.
- Public service: `pages.repository`.
- Public pages render through the active theme's logical `page` template with a module fallback.

### Menus 0.1.0

- Named reusable menus with stable handles.
- Arbitrarily nested items, ordering, enabled state and new-window links.
- Drag-and-drop administration with non-drag parent selection fallback.
- Public service: `menus.repository`.
- Implements the Core `MenuSourceInterface`; it does not know theme location names.

## Themes

### mCMS Default 0.1.0

- Built-in responsive public theme.
- Logical `home` and `page` templates.
- Header navigation with mobile collapse and two-level dropdown support.
- Footer navigation and legal-link locations.
- Theme-declared locations:
  - `header.primary` — maximum depth 2;
  - `footer.primary` — maximum depth 1;
  - `footer.legal` — maximum depth 1.
- Theme assets are copied into the nginx runtime; PHP/view files are not exposed by the web server.
- If no explicit theme has been selected, the built-in default theme is used when present.

## Menus ↔ themes

- Assignments are managed from the Themes administration screen.
- Assignments are persisted per theme in Core settings.
- Only enabled menu items are supplied to public theme rendering.
- Theme `max_depth` is enforced by Core before rendering.
- Disabling Menus does not remove assignments or break the public theme; navigation becomes empty until Menus is enabled again.

## Verification

The checkpoint is covered by:

- PHP lint and Composer validation;
- Core unit/integration tests;
- clean-install Docker smoke through real administration forms;
- public theme asset checks;
- public Home/Pages theme rendering checks;
- menu assignment, Menus disable/re-enable and assignment restoration checks.

## Next sequence

1. News.
2. Galleries.
3. Commerce modules after the reusable content/presentation layer is stable.
