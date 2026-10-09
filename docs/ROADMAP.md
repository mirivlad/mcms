# mCMS roadmap

## Stage 0 — modular Core

- [x] Initialize independent repository with no inherited Git history.
- [x] Move CodeIgniter application layout to repository root.
- [x] Remove customer-specific runtime/deployment configuration.
- [x] Remove dependency on an imported legacy database.
- [x] Adopt CodeIgniter Shield for authentication/authorization.
- [x] Adopt CodeIgniter Settings for persisted configuration.
- [x] Define module manifest/provider lifecycle.
- [x] Add database-backed module registry.
- [x] Ensure module migrations are namespace-scoped and opt-in.
- [x] Add initial Tabler-based admin shell.
- [x] Add browser setup wizard, CLI fallback and first superadmin creation.
- [x] Add Core user/group/permission administration.
- [x] Add Core settings UI.
- [x] Grouped central settings with original module entry points retained and shared form handlers.
- [x] Add diagnostics/system page.
- [x] Add English/Russian localization and site locale selection.
- [x] Add local/runtime packaging for Bootstrap and Tabler assets.
- [x] Enforce mCMS and module dependency version constraints.
- [x] Add module lifecycle and fresh-install integration tests.

## Stage 1 — first reusable modules and public presentation

- [x] Media.
- [x] Pages.
- [x] Menus/navigation.
- [x] Theme API and administration.
- [x] Default theme with declared menu locations.
- [x] Menus ↔ theme-location assignment.
- [x] News.
- [x] Galleries.
- [x] Gallery shortcodes in Pages and News.
- [x] Second first-party theme: mCMS Signature.
- [x] Selected published page as Home.
- [x] Feedback form and administration inbox.
- [x] Optional sitemap and moved-resource redirects.
- [x] Media previews and explicit used-file deletion confirmation.
- [x] Content-list pagination and public-path conflict protection.

## Content workflow and customization — approved follow-up

- [x] SEO title/description templates and manual/tag-derived keywords.
- [x] Private unsaved Pages/News preview in the active theme.
- [x] Autosave/recovery and revision history.
- [x] Per-theme logo, favicon, color and contact settings.
- [x] News categories, tags, filters and public search.

## Stage 2 — commerce modules

- [x] Define the product-image boundary: dedicated images, independent of Media.
- [x] Define first-stage Catalogue product/variant/price/stock/image contracts; see [commerce requirements](COMMERCE.md).
- [x] ProductImages: multiple uploads, crop/scale editor, main-image selection and drag-and-drop ordering.
- [x] Catalogue: products, variants/RUB prices, editor and public galleries in all themes.
- [x] Cart: guest/session cart, live Stock checks without reservation, immutable line snapshots and automatic quantity correction.
- [x] Orders: checkout snapshots, reserve-on-order, manager statuses, cancel release and completion write-off.
- [x] Stock: variant-level on-hand/available quantities, reservations, audited corrections and manager quick controls.
- [x] Delivery: fixed-price delivery and pickup methods, admin ordering/enabled state and immutable order snapshots.
- [x] Export/report adapters: order totals by creation date/status/currency and full orders/items CSV from saved snapshots.

## Stage 3 — ecosystem

- [x] Module ZIP packaging/install, including locally exported repository sources.
- [x] Module compatibility/version constraints at discovery/enablement.
- [x] Safe external module update workflow with code snapshots, maintenance and explicit database-backup recovery.
- [x] Third-party theme ZIP packaging/install, review, separate activation and persistent static resources.
- [x] Optional API module: scoped Bearer keys, physical stock exchange with safe retries, saved orders/delivery and current delivery methods; see [API contract](API.md).
- [x] Optional order Notifications: recipient/events/templates, transactional outbox, test email and retry; see [delivery contract](NOTIFICATIONS.md).
- [ ] Further notification/integration modules as concrete requirements arise.

## Reference policy

Ferma is a functional reference only.

When mCMS needs a feature already implemented there, first extract the user-facing requirement and behavior, then implement it against mCMS Core contracts. Do not copy legacy schema, customer assumptions or compatibility routes unless they are independently justified for a reusable module.
