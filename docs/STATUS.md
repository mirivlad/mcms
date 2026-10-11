# Current baseline — 2026-10-09

This document records the current known-good mCMS implementation checkpoint.

## Core

- Core version 0.1.6 with optional Home, public-resource and path-conflict provider contracts.
- CodeIgniter 4.7.4, Shield 1.4.x and Settings 2.4.x.
- Database-backed module discovery, dependency resolution and lifecycle.
- Namespace-scoped module migrations; Core never runs `migrate --all`.
- Public module service registry, administration contribution registry and generic navigable-resource registry.
- Shared safe rich-text sanitizer used by content modules without introducing module-to-module dependencies.
- First-run browser installer plus CLI recovery bootstrap.
- Users, groups, permissions, Core settings and system diagnostics.
- Grouped settings centre retains original module forms and adds central aliases with shared validation/storage, section permissions and post-save navigation; see [settings](SETTINGS.md).
- Access groups shown in a responsive table/list with member and permission counts; creation and editing use a shared modal with input recovery after errors.
- Group deletion requires confirmation, atomically removes group grants and memberships, and preserves accounts, other groups and direct permissions. Core/default registration groups cannot be deleted.
- English/Russian localization.
- Tabler 1.6.1 administration shell with responsive navigation, a public-site link
  and permission-filtered collapsible Content, Appearance, Shop and Administration groups.
- Repeated list actions use compact Tabler icons with localized accessible names
  and hover/focus tooltips. Escape and activation dismiss the tooltip. Primary
  creation/save/confirmation actions retain text; mobile icon targets are 44px.
- HTTPS reverse-proxy support and standard CodeIgniter error views.
- Theme discovery, manifest validation, activation and logical template rendering.
- Per-theme menu-location assignments through the Core navigation source contract.
- Per-theme built-in Home text, button, cover and section settings for themes declaring `home_defaults`.

## Module packages

- CLI `mcms:module:pack` builds validated ZIPs from a module directory, including
  locally exported repository revisions, without executing their PHP.
- Administration → Modules → Install from ZIP checks compatibility and shows
  identity, version, dependencies, unpacked size and SHA-256 before confirmation.
- All package endpoints require `core.modules`; preview tokens belong to the
  authenticated session/user. Adding trusted code requires explicit confirmation.
- Path traversal, links, special/encrypted files, duplicate/case-conflicting paths,
  auto-discovered Config/Commands and oversized packages are rejected.
- Atomic publication into a persistent private package root does not run migrations
  or boot/enable the module. Explicit enablement uses existing dependency resolution
  and namespace-scoped migrations. A provider with an invalid interface or
  required constructor arguments is refused before migration/activation; disabling
  preserves its data.
- The installation form refuses existing ids/namespaces. A refresh action on
  external modules provides ZIP updates with unchanged identity/provider, newer
  versions, forward/reverse compatibility and retained historical migrations.
- Preparation preserves installed code/DB, saves a downloadable code snapshot and
  can be cancelled. Apply requires an external full database backup confirmation;
  it drains normal requests, runs a fresh target-only migration worker in persistent
  maintenance and keeps the module disabled after success.
- Failure/interruption keeps public/business requests at HTTP 503. Core recovery
  survives app recreation; startup migrations/demo writes are skipped. After an
  operator restores the full database backup, Core checks registry/migration history
  and restores the previous code without down migrations. Code snapshots do not
  back up/restore the database. The workflow supports the single-app Docker stack.
- Remote repository retrieval remains future work.
- Opted-in demo initialization discovers enabled external packages as well as
  first-party modules; its one-time content marker and existing package state are preserved.
- Docker and Portainer persist external code in `modules_data`; back up
  this volume with the database. Package inspection is not a PHP sandbox.

## First-party modules

### Media 0.2.2

- Persistent media library with upload and metadata editing.
- Configurable upload/image limits and MIME allow-list.
- Public services: `media.library` and `media.usage`.
- Cached proportional previews, image-only browsing and administration pagination.
- Separate used-file deletion confirmation listing references from installed content modules, including disabled ones.

### Pages 0.6.1

- Draft/published static pages with sanitized rich text, including nested unknown HTML wrappers.
- Tags and manual keywords; private unsaved preview through the active theme without database writes.
- Paginated administration and fixed module-prefix reservation with legacy conflict detection.
- Optional published page selection for Home with safe built-in fallback.
- Slug generation, SEO fields and Media picker integration.
- Public service: `pages.repository`.
- Published pages register as generic navigable resources through provider `pages.page`.
- Public pages render through the active theme's logical `page` template with a module fallback.

### News 0.6.1

- Draft and published news articles with optional future publication time.
- Tags and manual keywords; private unsaved preview with featured image and shortcode rendering.
- Sanitized rich text, excerpt, SEO metadata and optional featured Media image.
- Public archive at `/news` and articles at `/news/{slug}`.
- Module and central settings expose public page size and initial status of new article forms; current articles keep their status.
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
- Shared display assets in all first-party themes and Pages/News fallbacks; independent slider state, keyboard controls and responsive layouts.
- Disabled albums preserve data and remain visible to Media usage inspection.

### Feedback 0.1.0

- Public contact form with CSRF, honeypot, throttling and server validation.
- Private paginated inbox with read/delete permissions; disable preserves messages.
- Theme template, fallback and generic contact navigation resource.

### SEO 0.3.0

- Home/Pages/News title and description templates with `{title}`, `{site_name}` and `{tags}`.
- Default keywords or tags as keyword source, overridden by manual per-content metadata.
- Metadata provided through the generic Core theme metadata interface; disabling SEO preserves settings and content.

- Public sitemap enumerates enabled providers beyond search-result limits.
- Sitemap `lastmod` uses actual Pages/News/Galleries modification dates and the selected published Home page; unknown dates are omitted.
- Stable-resource 301 redirects for slug changes; reused live URLs take precedence.
- Draft, deleted and disabled targets do not redirect.
- Manual local 301 redirect creation/editing/deletion, duplicate/live/system-source and cycle validation; chains resolve in one response.
- Redirect administration and disable/re-enable persistence; manual published-content targets follow stable resource IDs.

### ProductImages 0.1.0 / Catalogue 0.2.4

- Catalogue depends only on ProductImages; Media/Galleries are independent.
- Private persistent originals and generated WebP revisions, validated uploads,
  EXIF orientation, repeatable crop/scale, main photo and ordered image sets.
- Product editor with multiple-file chooser/drop uploads, per-file progress/errors,
  crop dialog, drag-and-drop, keyboard/touch move buttons, replacement and removal.
- Draft/published products, nested categories with slugs and image/Tabler icons,
  and stable variants with unique
  uppercase SKUs and integer RUB prices.
- `/shop` listing/detail templates in all four themes, ordered thumbnails,
  enlarged gallery with keyboard/swipe controls and variant price/SKU selection.
- Published navigation/search/sitemap resources and generic slug-move redirects.
- Module disable/re-enable preserves data; draft images and originals stay private.
- Public catalogue page size is configurable in Store management and the central settings; the same limit applies to categories/filters.
- Catalogue 0.2.4 exposes stable variant lookup/search and optional Cart storefront controls for dependent commerce modules.

### Stock 0.1.1

- Separate Catalogue-dependent module; Catalogue remains usable with Stock disabled.
- Physical on-hand, reserved and available quantities are keyed by stable variant ID.
- Absolute manual corrections are audited and may not reduce physical quantity below reservations.
- Reservation API supports create/update, release, expiry and consumption. Orders uses it after checkout; Cart only reads availability.
- Standalone administration supports search and per-variant correction.
- Users with stock.manage get compact correction controls in Catalogue product lists,
  existing product editors and authenticated public storefront cards/product pages.
- Deleting a Catalogue variant cascades live stock and reservations while retaining movement history.

### Cart 0.1.1

- Separate module depending on Catalogue and Stock; no commerce state is added to Core.
- Guest and authenticated carts are session-scoped and deliberately do not reserve stock.
- Cart lines snapshot product title/slug, SKU, variant label, integer RUB unit price and currency.
- Add/update/open checks current Stock availability and clamps stale quantities to the live maximum.
- Product cards and detail pages expose add-to-cart controls only while Cart is enabled.
- The public cart saves quantity changes automatically without an Update button; overstock requests are clamped and highlighted.
- The cart explicitly warns that items are not reserved until an order is placed.
- All four bundled themes provide the logical cart.index template.
- Cart 0.1.1 integrates optional Delivery methods into checkout and shows item, fulfillment and grand totals; if Delivery is enabled with no active methods, checkout is blocked.

### Orders 0.3.2

- Checkout creates immutable order/item snapshots from the current Cart.
- Stock is reserved only after the order is successfully created; Cart itself never reserves inventory.
- A race that makes Stock unavailable during checkout aborts the order instead of silently reducing it.
- Manager workflow supports new, processing, ready, completed and cancelled states with history.
- Cancelling releases the reservation without changing physical stock; completing consumes the reservation and writes stock off.
- Administration supports order search, filtering, details and status changes.
- Active orders hold a database-level variant lock; terminal orders release it while keeping immutable order snapshots.
- Orders 0.2 accepts a server-resolved fulfillment snapshot and stores method ID/type/name, fulfillment charge and grand total independently of later Delivery edits.
- Read-only order reports with inclusive creation-date/current-status filters and
  counts/item/delivery/grand totals grouped by status and currency.
- Full orders/items CSV exports from historical snapshots, exact integer minor-unit
  amounts, formula-safe text, 500-row query batches and private temporary downloads.
- Separate `orders.export` permission in addition to `orders.view`; reports and
  exports do not modify orders, inventory, reservations or the cart.

### Notifications 0.1.0

- Optional Orders-dependent module; delivery is off by default.
- One manager recipient, selected order events, plain-text templates and a test email.
- Own administration and a section in the Settings centre use the same handlers.
- Transactional outbox keeps committed order/item/delivery snapshots and retries SMTP failures without undoing orders or stock.
- Atomic sender claims, recent queue states, manual retry and `notifications:send` CLI; at-least-once delivery after crash recovery.
- Separate `notifications.manage` permission; disable/re-enable preserves preferences and queue.
- See [notification delivery and operations](NOTIFICATIONS.md).

### Delivery 0.1.1

- Separate module compatible with Orders 0.2 and 0.3; Orders remains usable when Delivery is disabled.
- Supports ordered fixed-price `delivery` and `pickup` methods with an enabled flag.
- Administration provides create/edit/delete and explicit display ordering under Commerce → Delivery & pickup.
- Prices are integer minor units; checkout submits only a method ID and the server resolves the current name/type/price.
- When enabled, Delivery requires one active method for checkout; an empty enabled Delivery configuration blocks checkout with a clear message.
- Orders snapshot the selected method, so editing or deleting a method never rewrites historical orders.

### Api 0.1.1

- Optional `/api/v1` HTTP/JSON integration for external accounting, including a client written for 1C; see [API contract](API.md).
- Scoped Bearer keys created/revoked in Administration → Integration API; optional expiry, one-time secret display and hash-only storage. Access tracks the current active owner's permissions.
- Variant inventory reads include physical/reserved/available counts and editable SKU identity. Physical updates compare the expected count under a current row lock and preserve reservations.
- Successful corrections and per-key idempotency receipts commit together. Retries after order consumption return the original receipt without restoring consumed stock; stale writes and reuse with a different operation return 409.
- Stable ID pages export saved order/customer/item/delivery snapshots, including deleted catalogue references. Inclusive update filtering supports overlapping polling and full reconciliation; this is not a durable event stream.
- Current enabled/disabled delivery methods are optional. Disabling Delivery does not hide order delivery snapshots; disabling Api preserves keys/receipts and commerce data.
- Session cookies do not authorize exchange. Admin forms retain CSRF; only Bearer API routes bypass it. Module recovery returns JSON 503 with Retry-After.
- HTTP/JSON is the agreed first contract. A ready-made 1C extension, CommerceML exchange, shipment tracking and order writes remain outside this stage.

## Themes

### mCMS Default 0.4.1

- Built-in responsive public theme.
- Logical Home, Pages, News, Galleries and Feedback templates.
- Header navigation with mobile collapse and two-level dropdown support.
- Footer navigation and legal-link locations.
- Theme-declared locations:
  - `header.primary` — maximum depth 2;
  - `footer.primary` — maximum depth 1;
  - `footer.legal` — maximum depth 1.
- First-party theme assets are copied into the nginx runtime; PHP/view files are not exposed by the web server.
- External ZIP themes live on the private `themes_data` volume. Core serves only validated static asset types, with versioned URLs, MIME/ETag/nosniff and SVG script restrictions.
- Themes → Install theme ZIP validates archive paths/limits, templates, namespace and Core compatibility without loading views. Trust confirmation installs an inactive theme; activation remains separate. SHA-256, preview expiry and locked collision checks protect the reviewed package.
- HTTP smoke covers permissions, confirmation/cancellation, duplicate rejection, static/private path separation, activation and settings after application recreation; the ZIP command is `mcms:theme:pack`. Browser review covered English/Russian at 320/390/1440px.
- If no explicit theme has been selected, the built-in default theme is used when present.

### mCMS Signature 0.2.1

- Second first-party theme with local assets, warm paper/violet branding and system fonts.
- All public templates plus the `galleries.embed` override.
- Public API-backed Home stories/albums, automatic public navigation when no menu is assigned.
- Native mobile/submenu disclosures, skip link, focus indicators and reduced-motion support.
- Gallery dialog with arrow keys, Escape, captions, original-image fallback and focus restoration.

### mCMS Depth / Focus 0.1.1

- Two separate themes: deep blue landscape with orange accents, and a red/white magazine with a night-city cover.
- Local generated WebP covers and a bundled Cyrillic Manrope font with its OFL license.
- Complete Home, Pages, News, Galleries, Feedback and Search templates, including embedded galleries.
- Editable built-in Home under Themes → theme settings: text, button, Media cover, introductory section and News/Galleries toggles.
- Independent settings retained when switching themes; a selected published Pages Home keeps priority.
- Shared Media usage inspection includes Home covers in inactive themes.
- Native mobile navigation, keyboard gallery dialog, skip links, focus indicators and reduced-motion support.

## Menus ↔ themes

- Assignments are managed from the Themes administration screen.
- Assignments are persisted per theme in Core settings.
- Only enabled menu items are supplied to public theme rendering.
- Theme `max_depth` is enforced by Core before rendering.
- Disabling Menus does not remove assignments or break the public theme; assigned navigation becomes empty until Menus is enabled again. Signature, Depth and Focus fall back to automatic public navigation.

## Verification

The 2026-10-09 update checkpoint passed 171 PHPUnit tests / 1190 assertions and
PHP lint. Focused tests cover trusted-code staging without execution, cancellation,
version/identity constraints, dependent modules, stale source/candidate checks,
historical migration preservation, runtime reader/shutdown draining, interrupted receipts
and failed-update recovery with database baseline checks. Production Docker/MariaDB
HTTP verification covers permissions, confirmation, code snapshot download,
successful scoped migrations with preserved records and refreshed PHP code,
failed DDL, persistent HTTP 503, startup migration suppression, app/web recreation,
full database restore and code recovery. Real browser forms were exercised in
English/Russian at 320/390/1440 px without horizontal overflow, including successful
apply, separate re-enable and recovery after a real failed DDL migration. The
update/recovery smoke is part of clean-install CI.

The 2026-10-09 package checkpoint passed 152 PHPUnit tests / 1151 assertions,
PHP lint and Compose validation. Focused package tests cover malformed/unsafe ZIPs,
compatibility/dependency cycles, stale concurrent previews, checksums, expiry and
cancellation, and installation/discovery without provider execution. A fresh
MariaDB production Docker image (authoritative Composer class map) passed HTTP
review, confirmation, access denial, cancellation, collision rejection, actual
external-module enablement and scoped migrations. Rebuilt/recreated app/web
containers retained its code, enabled state and exactly one migration-created row;
disable/re-enable preserved that row. The real browser upload/review was checked in English/Russian at
1440px, 390px and 320px without horizontal overflow; the Russian add/enable
workflow and native required confirmation were exercised end to end. The package and recreation
smoke checks are part of the clean-install workflow.


The 2026-10-08 administration presentation checkpoint passed PHP lint and focused
navigation/localization/manifest checks (10 tests / 30 assertions), following the
fresh baseline suite (111 tests / 1046 assertions). The real disposable admin was
inspected in Russian and English: Pages, News/categories, Galleries, Media,
Catalogue/categories, SEO, users/groups, modules, themes, Delivery and Menus.
Browser checks covered hover/focus tooltips, Escape, edit/delete dialogs and Media
copy feedback. Pages, category, Delivery and access screens were checked at 320px,
390px and desktop widths without page overflow; wide tables retain internal scrolling.
The category header now wraps its buttons on narrow screens.

Orders reporting verification includes 116 PHPUnit tests / 1097 assertions, PHP
lint and Composer validation. A fresh MariaDB Docker installation passed the
site and commerce HTTP smoke plus `orders-reports-smoke.py`: inclusive dates,
status filters, historical fulfillment/line prices, exact totals, CSV headers,
invalid inputs, separate view/export grants and module disable/re-enable.
Focused tests cover mixed currencies, 502-row exports across query batches,
empty results, missing live product references, multiline/quoted text and
spreadsheet formula markers. The real browser workflow covered report discovery
from Orders, filtering, invalid/empty states and a successfully downloaded CSV
whose saved integer totals were checked. Russian/English report layouts were
inspected at desktop, 390px and 320px widths without page overflow.
The administration UI commit `4a6ec71` passed both GitHub workflows. No live-server
deployment is recorded for these changes.

The sidebar checkpoint covers automatic group expansion on nested routes,
subdirectory-aware public links, legacy module contributions, module disablement
and user permission filtering. The isolated HTTP check exercised actual admin
routes with all first-party modules and a Pages-only editor account. Anonymous
copies of the rendered shell were inspected at desktop and 390px widths for
disclosure clicks/keyboard controls, mobile toggling and scrolling without
horizontal overflow; no authenticated browser session was used.

Fresh verification for this checkpoint includes 111 tests / 1046 assertions,
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

SEO/preview HTTP checks additionally cover all four themes, per-content overrides, tag-derived keywords, invalid-rule rejection, unchanged published content after preview, rotated CSRF tokens, create-only editor restrictions and draft-news dates. Preview UI was exercised on unauthenticated copies of real editor forms with fixture responses.

The Depth/Focus checkpoint also covers independent Home settings, escaped text,
button URL validation, cover usage in inactive themes, image-source disablement and
published Pages Home priority. The clean-install HTTP smoke exercises Home forms,
invalid input recovery, section flags and permissions, plus public rendering,
embedded galleries, search and assets for all four themes. Depth/Focus Home and
content layouts were checked in an anonymous browser at desktop, 390px and 320px
widths, including mobile menu Escape and gallery keyboard/focus behavior. The Home
Media picker was exercised in an unauthenticated copy of the rendered form with
fixture responses: cover/logo isolation, selection/reset and focus restoration.
Catalog previews are actual screenshots from the temporary site. No live-server
deployment is recorded by this checkpoint.

Catalogue verification additionally covers real PNG/JPEG processing, original hashes,
crop output pixels/dimensions, EXIF rotation, file/pixel/photo limits, rejected
cross-set actions, variant identity, exact prices, sanitization and expired pending
sets. The dedicated HTTP smoke checks multipart uploads, repeated edits, order/main
persistence, replacement/removal, draft privacy, editor permissions/user isolation,
SEO/search, four themes, Media disablement and product deletion. Interactive editor
checks used an unauthenticated copy of the actual rendered form with fixture
responses: pointer cropping, output sizes, drag-and-drop, keyboard ordering, main
selection, variant creation and desktop/390px layouts. Public gallery controls were
checked on the real anonymous storefront. Recreating app/web containers preserved
the published product and the exact derivative bytes on the private image volume.
The final checkpoint was not deployed to
a live server.

## Next sequence

The approved content workflow and first commerce stage are implemented. Catalogue
and ProductImages work independently of Media/content Galleries, with a dedicated
private storage volume, preserved originals, multiple photos, crop/resize, explicit
main selection, drag-and-drop and keyboard/touch ordering, replacement/removal.
Catalogue stores products, nested categories, stable variants and integer RUB
prices. All four themes render listing/detail pages, enlarged image galleries and
variant selectors. Published product resources participate in search, navigation,
sitemap and automatic slug redirects.

Stock 0.1.1 owns variant-level on-hand quantities, reservations and availability.
Managers can correct stock from the standalone Stock screen, Catalogue product list,
product editor and the public storefront while authenticated. Corrections are audited,
reservations cannot exceed availability, expired reservations are released lazily, and
physical stock cannot be corrected below the reserved quantity. Public storefront
corrections now submit through AJAX and update the displayed availability without a page reload.

Cart 0.1.1 checks live availability but never reserves inventory. It keeps immutable
line-price snapshots, clamps stale quantities, saves quantity edits automatically and
shows optional Delivery selection/totals at checkout. Orders 0.3.2 is the reservation
boundary and stores immutable fulfillment snapshots plus item/delivery/grand totals.

Delivery 0.1.0 provides ordered fixed-price delivery and pickup methods. The browser
submits only the selected method ID; its current price and type are resolved server-side
before Orders snapshots them. Orders remains usable with Delivery disabled.

Order reports and CSV adapters are implemented for saved order/line snapshots.
Module packaging, installation and the external module update workflow are now
implemented. Third-party theme ZIP packaging/installation is also available;
see [theme packages](THEMES.md). Api now provides the agreed inventory and order/delivery
HTTP/JSON contract; see [integration API](API.md). Notification modules and concrete
external accounting clients remain separate stages.
See [commerce contracts](COMMERCE.md) and the modules' README files.

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
two-segment route preserves existing root-level `/search` pages. All four themes expose
search and taxonomy links. Verification includes migration up/down, category guards,
filter-preserving pagination, literal wildcard queries, module disable/re-enable,
real HTTP forms and unauthenticated category dialogs/public mobile search.

Catalogue 0.2.4 includes the 0.2.0 category-tree work: editable slugs, a visual Tabler picker with
color selection, and independent category-image uploads. Existing text labels
migrate into root categories while retaining product assignments. Parent sections
include descendant products; categories participate in Menus, sitemap and slug
redirects. Cycles and deletion of categories with products or children are blocked.
Verification: 87 PHPUnit tests / 851 assertions; legacy migration up/down on SQLite
and MariaDB; Docker HTTP smoke across all four themes, category CRUD, uploads,
replacement, appearance switching, assignment, descendant filters and redirects.
Anonymous copies of actual forms were checked in a browser for icon search/paging,
selection/color, file preview, delete confirmation and 390px layouts without overflow.

Optional demo data is disabled by default (`MCMS_DEMO_SEED=0`). Explicit opt-in
adds 5 pages, 6 news articles, 12 products, 6 nested categories and 2 redirects,
once per database. A persistent completion marker prevents repeated redeploys
from restoring deleted demo content or changing manual edits. Existing content
and site/theme/menu settings are preserved. Module initialization precedes an
atomic content/marker transaction; failure does not mark the bundle complete.
Verification: 91 PHPUnit tests / 910 assertions, default-off and opted-in Docker
installation, seeding an existing installation at redeploy, public HTTP content,
draft privacy, two 301 redirects and unchanged repeat-command snapshots. Recreated
containers retained original content, manual demo edits/deletion and the marker.

Catalogue administration now opens Store management with links to products and
categories. Both lists return to that page; the product list and editor no longer
link directly to category management. Category image upload is always visible
alongside the Tabler picker. Selecting a file selects image appearance; selecting
an icon in the modal selects Tabler appearance. Verification: 91 PHPUnit tests /
910 assertions, Docker HTTP smoke including uploaded/replaced category images,
store navigation and product deletion, Russian HTTP views, and anonymous browser
form/navigation checks including a 390px viewport without horizontal overflow.


## Integration API checkpoint — 2026-10-09

- 213 PHPUnit tests / 1328 assertions passed, including hashed/scoped keys, owner
  permissions, expiry/revocation, inventory reservations, stale updates, receipt
  rollback, immutable order snapshots and module disable/re-enable.
- A fresh Docker/MariaDB stack completed real HTTP key creation, cookie/CSRF
  isolation, body/filter validation, delivery editing/disablement, checkout and
  consumption. Competing independent writers with the same baseline produced one
  success and one conflict; parallel retries returned one saved receipt. Case-only
  operation keys remained distinct under MariaDB collation. An injected MariaDB
  receipt-insert failure returned JSON 500 and rolled back stock and its audit.
- App/web recreation preserved the API credential, saved receipt, stock and order;
  replay with the original expected count succeeded through its retained receipt.
  Module maintenance returned JSON 503 and Retry-After. The reusable HTTP/recreation
  check is included in clean-install CI.
- The key creation/use/one-time display/revoke flow was operated in a real browser.
  EN/RU layouts were checked at 320/390/1440px; the document remained within the
  viewport and compact revoke controls use localized tooltips and 44px mobile targets.
- PHP lint and Python syntax checks passed. This checkpoint was not deployed to a
  live server; real 1C-side adaptation has not been exercised.

## Settings and notifications checkpoint — 2026-10-09

- Core 0.1.6 groups settings sections contributed by enabled modules. Original
  module entry points remain available and share handlers/state with central aliases.
- News 0.6.1 exposes initial article status and public page size; Catalogue 0.2.4
  exposes public product page size. HTTP checks create real articles/products and
  verify pagination, editor defaults, validation and shared settings.
- Notifications 0.1.0 uses Orders 0.3.2 events and a transactional outbox. Real
  HTTP/SMTP checks cover saved templates/recipient, selected events, failed SMTP
  during successful checkout/reservation, retry, no-op transitions, CLI, permissions
  and module lifecycle. A disposable loopback SMTP receiver never relays mail.
- Focused PHPUnit checks passed: 24 tests / 185 assertions. The full suite ran
  222 tests / 1472 assertions with one existing Atelier autoloader test failure
  introduced by the separate theme-example commit; other tests passed.
- Actual browser forms were operated in the disposable local stack. EN/RU settings
  and notifications were checked at 390/1440px without document overflow. Server
  mail relay configuration and production deployment remain operator tasks.
