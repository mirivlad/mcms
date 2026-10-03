# Commerce requirements and next implementation stage

Status: planning; commerce modules are not implemented yet.

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

## Proposed module boundary

Working name: **ProductImages**. It owns original files, image processing,
generated sizes and its editing controls. It provides a public module service for
Catalogue, rather than exposing its tables or filesystem layout to consumers.
Product images have their own storage and identifiers; they are not `media_files`
rows, Media IDs or shared-library picker entries. Content Galleries is also not a
shop dependency, because it requires Media.

**Catalogue** owns products, variants and the product editor. Its image section
uses ProductImages controls and public services. The storefront receives the
ordered product-image data through Catalogue's public presentation API and renders
it through logical theme templates. Core remains independent of commerce.

The final image-assignment schema, service signatures and lifecycle will be
specified together with the Catalogue contract before runtime implementation.

## Proposed product editor workflow

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

This is a proposed UX for the first implementation, not an implemented screen or
a decision to copy Ferma's database or controllers.

## Work sequence

1. Inspect the CI4 Ferma product editor, image handling and storefront; record the
   useful behavior and the single-image limitation.
2. Agree the first Catalogue scope and product/variant/price/stock/image contracts.
   Resolve image assignment, new-product uploads, removal and module lifecycle as
   part of that contract.
3. Implement ProductImages and its editor controls, then integrate Catalogue and
   public product templates. Verify editing and storefront behavior together.
4. Continue with Cart, Orders, Stock, Delivery and export/report adapters.

Product/variant image sharing, image sizes and supported upload limits are not
fixed by this document. Price/currency and stock/reservation rules still belong to
the next contract-definition step.

## Verification targets for implementation

- Catalogue and its images work with Media and content Galleries disabled.
- Multiple uploads, repeatable cropping/scaling and source preservation work on
  actual files.
- Main-image selection and drag-and-drop order survive saving and reopening.
- Product detail and listing images agree with the saved selection and order.
- Upload/edit actions enforce permissions, and mobile/keyboard controls work in
  the real product editor.
