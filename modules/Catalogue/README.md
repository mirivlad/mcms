# Catalogue

Enable **Catalogue** in Administration → Modules. ProductImages is enabled as its
sole dependency. **Store management** at `/admin/catalogue` links to products
(`/admin/catalogue/products`) and categories. Each list links back to store management.
The public catalogue is at `/shop`. Media and Galleries can remain disabled.

Products have a unique address, title, category, descriptions and publication
status. Add one variant for a regular product, or several variants for sizes/colors.
Each variant has a stable ID, unique uppercase SKU and a RUB price in integer
kopecks. Input accepts a dot or comma and at most two decimal places. Updating an
existing variant retains its ID. Categories are managed at `/admin/catalogue/categories`, with a parent, unique slug
and an uploaded JPEG/PNG/WebP image or a Tabler icon selected in a searchable modal.
The image upload is always visible; choosing a file selects image appearance.
Choosing an icon in the modal selects Tabler appearance. Tabler icons support a color picker. Images are resized to at most 256 pixels,
retain transparency, and live privately in the existing ProductImages volume.
The image limit is 5 MiB and 12 megapixels. Category images are public via Catalogue.

Products select one category from the tree. `/shop/category/{slug}` includes products
from the selected category and all descendants. Category pages participate in Menus,
sitemap and slug-change redirects. Public navigation includes categories with
published products and their ancestors. Delete is blocked while a category has
products (including drafts) or children; moving a category under its descendant is
blocked. Existing text labels migrate to root categories without losing product
associations; their generated `category-N` slugs can be edited.
The committed `assets/tabler-icons.json` lists the icons from the web image's
`@tabler/icons-webfont@3.48.0`; update that index when upgrading the bundled font.

The photo section provides multi-file selection/drop uploads with per-file progress,
crop/resize from retained originals, main selection, drag-and-drop ordering,
keyboard/touch move buttons, replacement and confirmed removal. Photo edits save
immediately; the product form saves product/variant fields and attaches a new set.

Public cards use the main photo. Detail pages have ordered thumbnails, switching,
an enlarged gallery with arrows/keyboard/swipes, and variant prices/SKUs. All four
bundled themes implement `catalogue.index` and `catalogue.product`. The standard
navigation provider exposes archive/products to Menus and SEO, and the public-search
provider includes published products. Slug changes emit the generic move event.
Only published products can serve associated image derivatives; editor previews
and raw original files are private.

This stage does not implement cart, checkout, orders or stock. Stock will own
availability/reservations by variant ID, rather than storing quantities in Catalogue.
See [commerce contracts](../../docs/COMMERCE.md) for the boundaries and next stages.

## Inventory identities (0.2.3)

`catalogue.exchange` implements `VariantFeedInterface`. `variantPage` exposes
variant/SKU identities, including draft products, in ascending permanent-ID pages
with a fixed `until_id` upper bound. It avoids title/position ordering changes
while an external accounting system reads inventory.

Catalogue 0.2.4 adds `catalogue.settings`, a Store management settings link and
central `/admin/settings/catalogue` alias to the same form/handler. Public shop
and category/filter pages use `catalogue.per_page` (1–100, default 24).
Administration pagination is unchanged; invalid values are rejected before saving.

## Catalogue navigation (0.2.5)

`/admin/catalogue/settings` now controls category navigation style:

- `tree` (default): all published category branches and descendants shown in a tree.
- `collapsed`: top-level categories initially visible, nested groups expanded with
  accessible buttons. The category name remains a navigable link.
- `horizontal`: top-level categories arranged horizontally with click/touch dropdowns;
  the layout becomes vertical on small screens. Escape closes open dropdowns.

The tree is generated **at render time** from published products' categories and
ancestors. Categories do not need to be copied into Menus or manually synchronized.
`catalogue.menu` implements `MCMS\Core\Themes\DynamicMenuSourceInterface` with
source ID `catalogue.categories`; it can be assigned to any theme menu location
under Admin → Themes → Configure → Additional menus, independently of the
shop-specific category navigation style. The theme's declared maximum depth
still applies in that location.

The same Catalogue settings page optionally selects an existing menu from Menus
as an additional shop menu, shown on listing/category pages or on all shop pages
(including product cards). When Menus is disabled, the configured ID is preserved
and the extra menu is hidden, not deleted. Other themes do not need changes.
