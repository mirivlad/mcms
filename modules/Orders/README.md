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

## Reports and CSV (0.3.0)

Open **Orders → Order report**. The default period is the current calendar month
through today. Filters use the order's creation date in the configured site time
zone, include both boundary days and accept an empty boundary for all earlier/later
orders. Status means the current order status. Invalid dates, reversed periods and
unknown statuses return 422 rather than silently removing a filter.

Counts and item/delivery/grand totals are grouped by status and currency, so cancelled
orders and different currencies remain separate. Amounts come from the immutable
order snapshots. They are order values, not proof of payment or an accounting ledger.
Reporting never changes orders, stock, reservations or the cart.

`orders.reports` implements `Api/ReportsInterface`: `summary()` and the CSV adapter
`writeCsv()`. The two exports use the same date/status filters and include every
matching order/line across pagination. Queries read 500 rows at a time in one read
transaction and the HTTP download uses a private temporary file removed at request
shutdown. Catalogue and Delivery names/prices are never joined into historical data.

- **Orders CSV**: one row per order; ID/status/dates, customer name/phone/email/comment,
  fulfillment ID/type/name, item subtotal, delivery charge, grand total and currency.
- **Order items CSV**: one row per saved line; line/order IDs, order status/creation
  date, product/variant IDs (possibly empty), historical title/SKU/variant label,
  quantity, unit price, line subtotal and currency.

Both formats use stable English column names, raw status codes, UTF-8 with BOM,
semicolon separators and CRLF records. Every `*_minor` amount is an exact integer
in the currency's minor units (kopecks for RUB); totals are not repeated per item.
Quotes/newlines are CSV-escaped. Text beginning with spreadsheet formula markers
(including after leading whitespace) is prefixed with an apostrophe, preserving
phone numbers as text and preventing formula evaluation.

Reports require `orders.view`. Downloads require **both** `orders.view` and the
separate `orders.export` permission, granted to the built-in admin group. A report
reader does not automatically receive access to exported customer data. Report and
download responses are private/no-store. Disabling Orders hides both routes while
preserving the source snapshots. Delivery accepts Orders 0.2 and 0.3.
