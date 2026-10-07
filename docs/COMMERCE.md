# Commerce requirements and next implementation stage

Status: Catalogue, ProductImages, Stock, Cart, Orders and Delivery implemented; export/report adapters remain later stages.

## First-stage data contracts

- `Catalogue` depends only on `product-images`; neither depends on Media or Galleries.
- A product has a permanent numeric ID, unique slug, title, optional category ID,
  short/plain description, sanitized HTML description and draft/published status.
- Catalogue owns a category tree with stable IDs, optional parents and globally
  unique editable slugs. Categories use a searchable Tabler icon picker and color,
  or a JPEG/PNG/WebP upload (5 MiB, 12 megapixels, resized to at most 256 pixels).
  Category icon files use Catalogue's `category-icons` directory inside the
  existing private ProductImages storage volume; Catalogue serves those icons.
  Each product selects one category; parent pages include descendant products.
  Existing category labels migrate without losing assignments. Cycles and deletion
  of categories containing products or children are rejected.
- Each product has at least one variant. A variant has a stable ID, globally
  unique uppercase SKU, a label (e.g. size/color) and its own price. A single unnamed
  variant is the normal product without options. Removed variant IDs are never
  reassigned to another variant. All variants share the product's image set.
- Prices are integer kopecks, currency RUB. Input accepts at most two decimal
  digits, using a comma or dot; no floating-point money, currency conversion,
  promotional prices or tax calculations in this stage.
- Catalogue does not own stock quantities. Stock owns on-hand quantities, active
  reservations and availability keyed by stable variant ID. Manual corrections are
  audited and cannot reduce on-hand below reserved quantity. Cart/order snapshots
  will copy SKU, label, unit price and currency rather than relying on mutable
  catalogue prices.
- Cart is session-scoped for guests and authenticated visitors and deliberately
  does not reserve stock. It checks current Stock availability on add/update/open and
  clamps stale quantities to the live maximum. This prevents abandoned or malicious
  carts from blocking inventory.
- Cart line snapshots copy product title/slug, SKU, variant label, integer unit price
  and currency when first added. Later Catalogue price edits do not rewrite an
  existing cart. Unpublished/deleted variants are removed during reconciliation.
- Public quantity changes are saved automatically after a short debounce; there is no
  separate Update button. A stock clamp returns the corrected quantity and the UI
  briefly highlights the affected row.
- Orders is the first reservation boundary. Successful checkout reserves every line
  with an `order:<id>` Stock key. Cancellation releases reservations; completion
  consumes them and writes physical stock off. Active orders hold variant locks so a
  Catalogue edit cannot delete a variant that is still reserved by an order.
- Delivery owns a small ordered catalogue of fulfillment methods: name, type
  (`delivery` or `pickup`), enabled flag and integer fixed price. When Delivery is
  enabled, checkout must select one enabled method; the submitted ID is resolved on
  the server, never trusted with a client-supplied price.
- Orders 0.2 snapshots fulfillment method ID/type/name/price and stores item subtotal,
  fulfillment charge and grand total. Editing or deleting a Delivery method therefore
  cannot rewrite historical orders. Orders remains usable when Delivery is disabled.
- `product-images.library` is the public API. ProductImages owns opaque image-set
  IDs, image IDs, originals, derivative revisions, crop recipes, order and main
  image. Catalogue stores only the unique image-set ID and consumes the API.
- Unsaved sets belong to their creating user; another user cannot attach/edit
  them. Attached sets are editable with `product-images.manage`. Photos save
  immediately; the editor says so. Saving the product attaches its set.
  Abandoned unsaved sets inactive for 24 hours are removed on the next set creation.
  Deleting a product deletes its owned image set, including originals.
- JPEG, PNG and WebP: up to 10 MiB/file, 12 megapixels, 20 images/product.
  EXIF orientation is respected. Crop uses pixels in the oriented original;
  output is limited to 300–2400 pixels on its longest side, without upscaling.
  240px thumbnail and 800px display derivatives accompany the selected full size.
  Repeated edits always start from the unchanged original.
- Originals live in private `writable/product-images` storage, on a dedicated
  Docker volume. Only Catalogue serves public derivatives, after checking the
  published product/image association. Draft images and originals stay private.
- `/shop` lists published products; `/shop/{slug}` renders the product gallery
  and variant selector. Product resources participate in navigation, search,
  sitemap and automatic redirects when a slug changes. All four bundled themes
  support these templates.

## Confirmed product-image requirements

- Catalogue and the shop must work independently of Media.
- Products use their own images, outside the shared content media library.
- A separate module handles product-image uploads, cropping, scaling and ordering.
- A product supports multiple images, rather than the single-image card in the Ferma reference.
- Editors can choose image order by drag and drop.
- The CI4 Ferma shop is a functional reference; mCMS should provide a more convenient product editor and storefront.

These requirements do not change Media, content Galleries or the existing themes'
content-media assignments.

## CI4 Ferma reference inspection

Inspected repository: `mirivlad/ferma`, branch `migration/ci4`, directory `ci4`,
commit `2d17599f260d8c0e55cda6fff49a7933510cc408`. This was a source inspection;
the reference application was not started or modified.

- [Product editor](https://github.com/mirivlad/ferma/blob/2d17599f260d8c0e55cda6fff49a7933510cc408/ci4/app/Views/shop_admin/item_form.php):
  one file input, staged upload, crop selection and one current image preview.
- [Catalogue persistence](https://github.com/mirivlad/ferma/blob/2d17599f260d8c0e55cda6fff49a7933510cc408/ci4/app/Services/CatalogAdminService.php):
  one `img` value per product, with category, SKU, descriptions and publication
  fields. Merchant-specific price/unit rules are reference behavior, not accepted
  mCMS contracts.
- [Image processing](https://github.com/mirivlad/ferma/blob/2d17599f260d8c0e55cda6fff49a7933510cc408/ci4/app/Services/MediaService.php):
  shop-specific staged files, crop finalization and a square 400×400 thumbnail.
  This service is not the mCMS Media module and will not be imported as a dependency.
- [Public product page](https://github.com/mirivlad/ferma/blob/2d17599f260d8c0e55cda6fff49a7933510cc408/ci4/app/Views/shop/item.php):
  one resolved image alongside product information and quantity/add-to-cart controls.
- [Sorting screen](https://github.com/mirivlad/ferma/blob/2d17599f260d8c0e55cda6fff49a7933510cc408/ci4/app/Views/shop_admin/sortable.php):
  drag and drop orders products within a category, rather than images within a product.

The useful reference is editing images in the product workflow. mCMS extends that
workflow to multiple images, explicit main-image selection, repeatable processing
and image ordering, with a separate product-image module and a public image gallery.

## Implemented module boundary

**ProductImages** owns original files, image processing,
generated sizes and its editing controls. It provides a public module service for
Catalogue, rather than exposing its tables or filesystem layout to consumers.
Product images have their own storage and identifiers; they are not `media_files`
rows, Media IDs or shared-library picker entries. Content Galleries is also not a
shop dependency, because it requires Media.

**Catalogue** owns products, variants and the product editor. Its image section
uses ProductImages controls and public services. The storefront receives the
ordered product-image data through Catalogue's public presentation API and renders
it through logical theme templates. Core remains independent of commerce.

Image assignment, prices, variant identity and lifecycle follow the first-stage
contracts above. Public service interfaces live in each module’s `Api` directory.

## Product editor workflow

1. Open a new or existing product and its **Images** section.
2. Add multiple files through a file chooser or a drop area; show upload progress
   and errors per file, so one failed upload does not obscure the others.
3. See all images as thumbnails, with a clear indication of the main image.
4. Edit an image in place: choose the crop area, scale it with proportions
   preserved, preview the result and save the edit.
5. Keep the original so the crop or scale can be changed again without repeated
   quality loss. Generate presentation sizes from that source.
6. Reorder thumbnails by drag and drop; provide move controls for keyboard and
   touch use. Choose the main image explicitly, and make replacement/removal
   available from the same section.
7. Save the product and retain the main image, processing choices and order when
   the editor is reopened.

The product listing uses the chosen main image. A public product page offers a
large image, ordered thumbnails, image switching and enlarged viewing, including
mobile layouts. It should remain usable with zero, one or several images.

Catalogue embeds the ProductImages editor; Ferma database/controllers are not copied.

## Work sequence

1. Inspect the CI4 Ferma product editor, image handling and storefront; record the
   useful behavior and the single-image limitation.
2. Agree the first Catalogue scope and product/variant/price/stock/image contracts.
   Resolve image assignment, new-product uploads, removal and module lifecycle as
   part of that contract.
3. Implement ProductImages and its editor controls, then integrate Catalogue and
   public product templates. Verify editing and storefront behavior together.
4. Implement Stock as a separate Catalogue-dependent module with absolute on-hand
   corrections, reservations and quick manager controls in administration and storefront.
5. Implement Cart with session ownership, immutable line snapshots, live Stock checks
   without reservation and automatic quantity updates.
6. Implement Orders. Successful checkout is the first point that reserves Stock;
   cancellation releases it and completion consumes it.
7. Implement Delivery as ordered fixed-price delivery/pickup methods and snapshot the
   selected server-resolved method into Orders.
8. Continue with export/report adapters.

The commerce contracts now cover product images, variants/prices, Stock, Cart, Orders
and Delivery. Export/report adapters remain separate implementation stages.

## Verification targets for implementation

- Catalogue and its images work with Media and content Galleries disabled.
- Multiple uploads, repeatable cropping/scaling and source preservation work on
  actual files.
- Main-image selection and drag-and-drop order survive saving and reopening.
- Product detail and listing images agree with the saved selection and order.
- Upload/edit actions enforce permissions, and mobile/keyboard controls work in
  the real product editor.
