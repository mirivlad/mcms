# Current baseline — 2026-10-02

This document records the current known-good mCMS implementation checkpoint.

## Core

- Core version 0.1.2 with optional Home, public-resource and path-conflict provider contracts.
- CodeIgniter 4.7.4, Shield 1.4.x and Settings 2.4.x.
- Database-backed module discovery, dependency resolution and lifecycle.
- Namespace-scoped module migrations; Core never runs `migrate --all`.
- Public module service registry, administration contribution registry and generic navigable-resource registry.
- Shared safe rich-text sanitizer used by content modules without introducing module-to-module dependencies.
- First-run browser installer plus CLI recovery bootstrap.
- Users, groups, permissions, Core settings and system diagnostics.
- Access groups shown in a responsive table/list with member and permission counts; creation and editing use a shared modal with input recovery after errors.
- Group deletion requires confirmation, atomically removes group grants and memberships, and preserves accounts, other groups and direct permissions. Core/default registration groups cannot be deleted.
- English/Russian localization.
- Tabler 1.6.1 administration shell with responsive navigation.
- HTTPS reverse-proxy support and standard CodeIgniter error views.
- Theme discovery, manifest validation, activation and logical template rendering.
- Per-theme menu-location assignments through the Core navigation source contract.

## First-party modules

### Media 0.2.0

- Persistent media library with upload and metadata editing.
- Configurable upload/image limits and MIME allow-list.
- Public services: `media.library` and `media.usage`.
- Cached proportional previews, image-only browsing and administration pagination.
- Separate used-file deletion confirmation listing references from installed content modules, including disabled ones.

### Pages 0.3.0

- Draft/published static pages with sanitized rich text, including nested unknown HTML wrappers.
- Paginated administration and fixed module-prefix reservation with legacy conflict detection.
- Optional published page selection for Home with safe built-in fallback.
- Slug generation, SEO fields and Media picker integration.
- Public service: `pages.repository`.
- Published pages register as generic navigable resources through provider `pages.page`.
- Public pages render through the active theme's logical `page` template with a module fallback.

### News 0.3.0

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

### Galleries 0.3.0

- Draft/published albums with captions, ordering and optional cover.
- Paginated image picker using Media API; album removal preserves files.
- Public archive/album, theme fallbacks and generic navigation providers.
- Stable-ID gallery shortcodes in Pages/News/Home, following live publication state.
- Gallery buttons in Pages/News editors: published-album search, pagination and insertion at the saved cursor or selection.
- Grid, banner-slider, carousel and captioned-card views selected per insertion; the album editor generates each shortcode.
- Shared display assets in both first-party themes and Pages/News fallbacks; independent slider state, keyboard controls and responsive layouts.
- Disabled albums preserve data and remain visible to Media usage inspection.

### Feedback 0.1.0

- Public contact form with CSRF, honeypot, throttling and server validation.
- Private paginated inbox with read/delete permissions; disable preserves messages.
- Theme template, fallback and generic contact navigation resource.

### SEO 0.1.0

- Public sitemap enumerates enabled providers beyond search-result limits.
- Stable-resource 301 redirects for slug changes; reused live URLs take precedence.
- Draft, deleted and disabled targets do not redirect.
- Redirect administration and disable/re-enable persistence.

## Themes

### mCMS Default 0.3.0

- Built-in responsive public theme.
- Logical Home, Pages, News, Galleries and Feedback templates.
- Header navigation with mobile collapse and two-level dropdown support.
- Footer navigation and legal-link locations.
- Theme-declared locations:
  - `header.primary` — maximum depth 2;
  - `footer.primary` — maximum depth 1;
  - `footer.legal` — maximum depth 1.
- Theme assets are copied into the nginx runtime; PHP/view files are not exposed by the web server.
- If no explicit theme has been selected, the built-in default theme is used when present.

### mCMS Signature 0.1.1

- Second first-party theme with local assets, warm paper/violet branding and system fonts.
- All public templates plus the `galleries.embed` override.
- Public API-backed Home stories/albums, automatic public navigation when no menu is assigned.
- Native mobile/submenu disclosures, skip link, focus indicators and reduced-motion support.
- Gallery dialog with arrow keys, Escape, captions, original-image fallback and focus restoration.

## Menus ↔ themes

- Assignments are managed from the Themes administration screen.
- Assignments are persisted per theme in Core settings.
- Only enabled menu items are supplied to public theme rendering.
- Theme `max_depth` is enforced by Core before rendering.
- Disabling Menus does not remove assignments or break the public theme; assigned navigation becomes empty until Menus is enabled again. Signature falls back to its automatic public navigation.

## Verification

Fresh local verification for this checkpoint includes 60 tests / 439 assertions,
PHP lint, Docker builds and the standalone HTTP form smoke in
`.github/scripts/site-smoke.py`. Public Signature Home, Galleries, embedded albums and Feedback were inspected in
the in-app browser, including a 390px mobile viewport, native menu toggling and
gallery keyboard navigation/Escape/focus restoration. Administrative forms were exercised via HTTP; interactive
Media-picker JavaScript was not exercised in an authenticated browser. The Gallery picker was additionally exercised in unauthenticated copies of server-rendered Pages/News forms: cursor/selection insertion, hidden-body synchronization, keyboard selection and Escape/focus restoration. Its real HTTP endpoint was checked for search, pagination, draft exclusion and editor permissions.
The display-mode checkpoint also covers slider independence, indicators and keyboard wrapping;
carousel scrolling and focus at its boundaries; Signature card lightbox integration;
390px responsive layouts without page overflow; and editor insertion/album shortcode generation for all views.
The access-management checkpoint also covers group creation/edit/deletion through HTTP forms,
duplicate/stale edits, server-side deletion confirmation and protected groups;
membership preservation and rollback after a simulated settings-write failure;
and unauthenticated copies of the rendered dialogs at desktop/390px sizes, including
permission selection, error input recovery, Escape/focus restoration and visible form actions.

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

1. Agree Catalogue product, variant, price and stock contracts.
2. Implement commerce modules after that agreement.
