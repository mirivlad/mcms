# Orders

Order checkout and manager workflow for mCMS commerce.

## Reservation boundary

Cart does not reserve inventory. During successful checkout Orders:

1. reads the reconciled immutable Cart snapshot;
2. creates the order and order-line snapshots;
3. reserves every line through `stock.inventory` using `order:<id>`;
4. creates active variant locks so Catalogue variants cannot disappear while the
   order owns a reservation;
5. commits the order and clears the Cart session.

If availability changes before the reservation can be acquired, checkout fails and
no reduced order is created silently.

## Status lifecycle

Supported states are `new`, `processing`, `ready`, `completed` and
`cancelled`.

- Active states keep the Stock reservation.
- `cancelled` releases the reservation without reducing physical stock.
- `completed` consumes the reservation and reduces physical stock.
- Terminal states remove the active variant lock but preserve the order/item snapshot
  and history permanently.

Administration provides search, status filtering, order details, status history and
manager notes. The module service is `orders.orders`.

## Delivery snapshot

Orders 0.2 accepts an optional fulfillment snapshot. When the Delivery module is
enabled, checkout resolves the submitted method ID on the server and copies its ID,
type, name and integer price into the order. Order totals are stored as item subtotal,
delivery/pickup charge and grand total. Historical orders do not depend on later
Delivery edits or deletion.
