# Current baseline — 2026-10-02

This document records the current known-good mCMS implementation checkpoint.

## Core

- CodeIgniter 4.7.4, Shield 1.4.x and Settings 2.4.x.
- Database-backed module discovery, dependency resolution and lifecycle.
- Namespace-scoped module migrations; Core never runs `migrate --all`.
- Public module service registry, administration contribution registry and generic navigable-resource registry.
- Shared safe rich-text sanitizer used by content modules without introducing module-to-module dependencies.
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
- Published pages register as generic navigable resources through provider `pages.page`.
- Public pages render through the active theme's logical `page` template with a module fallback.

### News 0.1.1

- Draft and published news articles with optional future publication time.
- Sanitized rich text, excerpt, SEO metadata and optional featured Media image.
- Public archive at `/news` and articles at `/news/{slug}`.
- Public service: `news.repository`.
- Generic navigation providers: `news.archive` and `news.article`.
- Public rendering through logical `news.index` / `news.article` theme templates with module fallbacks.
- Administration includes paginated article browsing and a searchable Media picker for featured and inline images.

### Menus 0.1.0

- Named reusable menus with stable handles.
- Arbitrarily nested items, ordering, enabled state and new-window links.
- Drag-and-drop administration with non-drag parent selection fallback.
- Public service: `menus.repository`.
- Implements the Core `MenuSourceInterface`; it does not know theme location names.
- Menu items support custom URLs or generic provider-backed internal resources without depending on Pages/News classes.
- Provider-backed targets survive provider/module disablement and resolve again after re-enable.

## Themes

### mCMS Default 0.2.0

- Built-in responsive public theme.
- Logical `home`, `page`, `news.index` and `news.article` templates.
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
- public Home/Pages/News theme rendering checks;
- menu assignment, Menus disable/re-enable and assignment restoration checks;
- generic Pages/News → Menus internal-resource discovery and resolution checks;
- News scheduling, sanitization, featured Media and public archive/article checks.

## Next sequence

1. Galleries.
2. Commerce modules after the reusable content/presentation layer is stable.
