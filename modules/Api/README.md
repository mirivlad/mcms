# Api 0.1.0

Optional HTTP/JSON integration module for external accounting (for example 1C).
Reads stock, order/customer/item/delivery snapshots and current delivery methods;
updates physical stock with expected-value checks and persistent idempotency.

Enable in Modules; manage scoped, expiring and revocable Bearer credentials in
Administration → Integration API. Key secrets are shown once and stored as hashes.
Only `/api/v1/*` bypasses session CSRF; admin key forms retain CSRF protection.

Commerce access uses `catalogue.exchange`, `stock.exchange`, `stock.inventory`,
`catalogue.repository`, `orders.exchange`, `orders.orders` and optional
`delivery.methods`. Api owns only key/receipt tables; existing commerce interfaces
remain compatible. Disable preserves data. Required module versions and permissions
are declared in `module.json`.

See [the complete v1 contract](../../docs/API.md) for endpoints, 1C client setup,
pagination, retries, timestamp semantics and error codes. No CommerceML adapter,
shipment ledger or order-status write endpoint is included in this stage.
