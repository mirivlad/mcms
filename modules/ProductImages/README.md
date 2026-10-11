# ProductImages

Enable through Administration → Modules, or enable Catalogue to resolve the
dependency automatically. There is no Media/Galleries dependency and no shared
media picker. Catalogue embeds this module's editor controls.

The `product-images.library` service implements
`MCMS\Modules\ProductImages\Api\ProductImagesInterface`. Consumers store opaque
image-set IDs and use this API; they do not read product-image tables or paths.
Upload/crop/order/remove controls require `product-images.manage`, in addition to
session authentication and CSRF protection. Unattached sets belong to their creator.

Original bytes and generated WebP revisions live in private
`writable/modules-data/data/product-images`. ProductImages automatically receives a directory within the shared
`modules_data` volume. Back up that volume alongside the
SQL database. Native installations must make the directory writable by PHP and
configure a 10 MiB upload limit, a 12 MiB request limit and 256 MiB memory.

JPEG, PNG and WebP files: 10 MiB, 12 MP and 20 images/set. EXIF orientation is
respected. Processing retains original bytes and validates crop coordinates before
resampling. `size` limits the longest side to 300–2400 px without upscaling; thumb
and display variants have 240 and 800 px limits. The authenticated editor's
`original` endpoint returns an oriented 1600 px preview, not the private raw file.

Photo changes save immediately. The first photo becomes main; removal of the main
photo selects a remaining one. Replacements keep the image ID, position and main
selection. All revisions are regenerated from the original; the prior revision
is removed only after the database update succeeds. Abandoned unattached sets
expire after 24 hours without photo changes, cleaned on the next set creation. Disabling the module keeps
its data; Catalogue must be disabled first. Product deletion removes its owned set.
