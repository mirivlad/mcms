# Catalogue

Enable **Catalogue** in Administration → Modules. ProductImages is enabled as its
sole dependency. The product editor is at `/admin/catalogue`, the public catalogue
at `/shop`. Media and Galleries can remain disabled.

Products have a unique address, title, category label, descriptions and publication
status. Add one variant for a regular product, or several variants for sizes/colors.
Each variant has a stable ID, unique uppercase SKU and a RUB price in integer
kopecks. Input accepts a dot or comma and at most two decimal places. Updating an
existing variant retains its ID. Categories are flat labels in this first stage.

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
