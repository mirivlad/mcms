# Delivery

Fixed-price delivery and pickup methods for mCMS commerce.

Delivery owns the selectable fulfillment catalogue only. Each method has a name,
type (`delivery` or `pickup`), integer price in minor units, display position and
enabled flag.

The module depends on Orders 0.2+, but Orders remains usable without Delivery.
When Delivery is enabled, checkout requires one enabled method. Orders copies the
method ID, type, name and price into its own immutable order snapshot. Editing or
deleting a method therefore never changes historical orders.

The public service is `delivery.methods`.
