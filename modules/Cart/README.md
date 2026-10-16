# Cart

Session-scoped customer cart built on Catalogue variants and current Stock availability.

## Behavior

- Guests and authenticated users get the same cart workflow.
- Putting an item into the cart does **not** reserve stock. This avoids allowing idle
  or malicious carts to block the shop inventory.
- Add/update operations clamp quantity to the currently available stock. Opening the
  cart reconciles it again, so quantities may fall if another real order reserved stock.
- Setting quantity to zero removes the row; clearing the cart only clears session state.
- SKU, variant label, product title and integer unit price are copied into the cart
  when the item is first added. Later Catalogue price edits do not rewrite that cart line.
- Existing cart items are removed if their variant disappears or their product is no
  longer published.
- A visible warning tells the customer that cart contents are not reserved for an order.

## Public UI

The Cart module provides `/cart` and a theme template named `cart.index`.
On supported theme pages, cart links open a responsive modal with the cart items via
`GET /cart/fragment`. Quantity changes, removal and clearing use AJAX; an empty cart
can be displayed in the modal without reloading the page. Checkout stays on the
separate existing `/cart#checkout` page. The normal `/cart` view remains available
for direct links and no-JavaScript fallback.

Catalogue
detects Cart only when the service is enabled and then adds add-to-cart controls to
product cards/detail pages plus a cart count on shop pages.

The cart page saves quantity changes automatically after a short debounce. There is
no separate Update button. If current Stock availability is below the requested
quantity, the server returns the corrected value and the row is briefly highlighted.

## Order boundary

Cart only validates the current availability. Orders owns the reservation boundary:
a successful checkout creates the order and only then calls Stock to reserve every
order line. If that reservation cannot be acquired because availability changed in the
race between the last cart check and checkout, the order is rejected instead of being
silently reduced.

## Service

`cart.cart` implements `CartInterface` and exposes the immutable line snapshots used
by Orders.

When the optional Delivery module is enabled, the checkout section lists its enabled
delivery/pickup methods and shows the item subtotal, fulfillment charge and live grand
total. If Delivery is enabled but has no selectable methods, checkout is blocked until
an administrator configures one.
