# Stock

Variant-level inventory for Catalogue.

Stock depends on Catalogue and stores physical quantity (on hand) and reserved
quantity separately. Catalogue remains usable when Stock is disabled.

## Administration

/admin/stock lists every Catalogue variant and supports direct absolute stock
correction. Users with stock.manage also get quick stock controls:

- in the Catalogue administration product list;
- on an existing product editor;
- on public /shop product cards and product pages while logged in.

A correction may not reduce physical stock below the currently reserved quantity.
Every physical correction is recorded in stock_movements.

## Service

stock.inventory implements StockInterface:

- level() / levels() return on-hand, reserved and available quantities;
- setOnHand() performs an audited absolute correction;
- reserve() creates or changes a caller-owned reservation;
- release() frees a reservation;
- consume() converts all reservations under a key into physical stock movement.

Reservations are keyed by the stable Catalogue variant ID. Optional expiry is
released lazily on subsequent stock operations. Cart/Orders can build on this API
without owning stock quantities.

## External synchronization (0.1.1)

`stock.exchange` implements `StockSyncInterface`. `compareAndSetOnHand` sets an
absolute physical quantity only when the supplied previous on-hand value matches.
Corrections acquire a database row write lock before reading the audit baseline.
A stale baseline or quantity below reserved stock throws `StockConflict` with the
current level; no reservation or movement is changed.
