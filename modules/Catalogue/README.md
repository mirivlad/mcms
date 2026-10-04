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
