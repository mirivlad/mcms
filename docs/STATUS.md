# Current baseline — 2026-10-03

This document records the current known-good mCMS implementation checkpoint.

## Core

- Core version 0.1.5 with optional Home, public-resource and path-conflict provider contracts.
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

### Media 0.2.1

- Persistent media library with upload and metadata editing.
- Configurable upload/image limits and MIME allow-list.
- Public services: `media.library` and `media.usage`.
- Cached proportional previews, image-only browsing and administration pagination.
- Separate used-file deletion confirmation listing references from installed content modules, including disabled ones.

### Pages 0.6.0

- Draft/published static pages with sanitized rich text, including nested unknown HTML wrappers.
- Tags and manual keywords; private unsaved preview through the active theme without database writes.
- Paginated administration and fixed module-prefix reservation with legacy conflict detection.
- Optional published page selection for Home with safe built-in fallback.
- Slug generation, SEO fields and Media picker integration.
- Public service: `pages.repository`.
- Published pages register as generic navigable resources through provider `pages.page`.
- Public pages render through the active theme's logical `page` template with a module fallback.

### News 0.6.0

- Draft and published news articles with optional future publication time.
- Tags and manual keywords; private unsaved preview with featured image and shortcode rendering.
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

### SEO 0.2.0

- Home/Pages/News title and description templates with `{title}`, `{site_name}` and `{tags}`.
- Default keywords or tags as keyword source, overridden by manual per-content metadata.
- Metadata provided through the generic Core theme metadata interface; disabling SEO preserves settings and content.

- Public sitemap enumerates enabled providers beyond search-result limits.
- Stable-resource 301 redirects for slug changes; reused live URLs take precedence.
- Draft, deleted and disabled targets do not redirect.
- Redirect administration and disable/re-enable persistence.

## Themes

### mCMS Default 0.4.0

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

### mCMS Signature 0.2.0

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

Fresh local verification for this checkpoint includes 66 tests / 572 assertions,
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

SEO/preview checks additionally cover both themes, per-content overrides, tag-derived keywords, invalid-rule rejection, unchanged published content after preview, rotated CSRF tokens, create-only editor restrictions and draft-news dates. Preview UI was exercised on unauthenticated copies of real editor forms with fixture responses.

## Next sequence

The approved content workflow continues with autosave/recovery and revision history,
per-theme branding settings, then categories/tags/filtering and public search.
Commerce remains a separate contract-definition stage.

1. Agree Catalogue product, variant, price and stock contracts.
2. Implement commerce modules after that agreement.

The editor workflow checkpoint adds private per-user autosave, explicit recovery/discard,
paginated revision history and confirmed restoration into the editor. Content and its
revision are committed together; autosave and preview never publish. HTTP smoke covers
editor permissions, incomplete drafts, user isolation, clearing on save and history
reads without publication. Unauthenticated copies of real forms cover autosave/reload/
recovery, sequential preview CSRF requests and version restoration at desktop/390px sizes.

Theme appearance now stores a per-theme logo/favicon assignment, accent and footer
contacts. Media supplies images through a Core-owned contract, so Core remains
independent of Media internals. Used-image deletion includes theme references;
missing or disabled Media falls back to theme branding while preserving assignments.
Verification covers independent values across both themes, safe contact/color
rendering, contrast-aware button/link colors, invalid-form recovery, image-picker
permissions, selection/reset/focus and 390px forms without horizontal overflow.

News now supports category management in create/edit modals, confirmed deletion of
unused categories, category selection in the editor and complete-tag filters in
admin/public archives. Tags are normalized without duplicate names. Category
references are protected by a database foreign key; taxonomy survives disablement.
Public search at `/search/results` aggregates enabled Pages/News providers, searches
titles/bodies/tags and excludes drafts and future news. Results are paginated in
module groups, excerpts remove HTML/shortcodes and search pages are noindex. The
two-segment route preserves existing root-level `/search` pages. Both themes expose
search and taxonomy links. Verification includes migration up/down, category guards,
filter-preserving pagination, literal wildcard queries, module disable/re-enable,
real HTTP forms and unauthenticated category dialogs/public mobile search.
