# Integration API v1

Api 0.1.1 provides HTTP/JSON exchange for an external accounting system, including
an integration written for 1C. It requires Catalogue 0.2.3, Stock 0.1.1 and Orders
0.3.1 or compatible later versions. Delivery is optional. Enable **Api** in
Administration → Modules; dependencies are enabled automatically. Then open
Administration → **Integration API** and create a named key with the necessary
scopes. Copy the secret when it appears: it is shown once, only SHA-256 is stored.
Use HTTPS outside a local test environment. Keys can expire and be revoked.

This is a versioned JSON contract. A 1C configuration must implement the HTTP
client below; a ready-made 1C extension and CommerceML protocol are not included.

## Access

Send `Authorization: Bearer <key>` on every `/api/v1` request. Query-string tokens
and login cookies do not authorize the API. Admin forms still require session
login and CSRF. Responses are private and not cached.

| Key scope | Current owner permission | Operations |
| --- | --- | --- |
| `stock.read` | `stock.view` | Read inventory |
| `stock.write` | `stock.manage` | Set physical stock |
| `orders.read` | `orders.export` | Read customer/order snapshots |
| `delivery.read` | `delivery.view` | Read delivery methods |

The owner must remain active, not banned and retain the required permission.
A key cannot grant permissions its creator does not have. Create separate keys
for independent integrations; revoke a key immediately if its secret is lost.
Disabling Api removes its routes and preserves keys, receipts and commerce data.

## Endpoints

Paths below are relative to `/api/v1`. IDs and quantities are JSON integers.
Money is in integer minor units; the current commerce implementation uses RUB.

| Method/path | Data |
| --- | --- |
| `GET /stock` | Page of variant identities and current stock, including drafts |
| `GET /stock/{variant_id}` | One variant's identity and stock |
| `PUT /stock/{variant_id}` | Absolute physical correction with conflict detection |
| `GET /orders` | Page of saved orders, customer/items/delivery and status |
| `GET /orders/{order_id}` | One saved order |
| `GET /delivery-methods` | Current methods, including disabled methods |

Success: `{"data": ..., "meta": {"api_version":"1", "server_time":"2026-10-09 12:00:00", "timezone":"UTC"}}`.
`server_time` is captured at the beginning of the request. All SQL-style timestamps
use the site's `meta.timezone`, including filters and key expiry.

Stock rows contain `variant_id`, `product_id`, `sku`, `label`, `product_title`,
`product_status`, `on_hand`, `reserved`, `available` and `updated_at`. Use stable
`variant_id` for writes: SKU is editable, and products may have several variants.
`available = on_hand - reserved`; a missing stock row reads as zero.

Orders contain `id`, `status`, `customer` (`name`, `phone`, `email`, `comment`),
`subtotal_minor`, `delivery_minor`, `total_minor`, `currency`, dates
(`created_at`, `updated_at`, `completed_at`, `cancelled_at`), `fulfillment`
(`method_id`, `type`, `name`, `price_minor`) and `items`. Each item contains saved
product/variant IDs, title, SKU, label, quantity and unit/subtotal prices/currency.
Deleted product/variant IDs may be null; saved descriptions remain. Later edits
or deletion of a delivery method do not rewrite order delivery.

Delivery methods contain `id`, `name`, `type` (`pickup`/`delivery`), `price_minor`,
`currency`, `enabled` and `position`. With Delivery disabled, this endpoint returns
503 `module_unavailable`; saved order delivery remains readable. There is no
shipment/tracking-number ledger in this stage.

## Stock update and retries

Read the current physical stock, then send an absolute new physical count:

```sh
curl -X PUT 'https://shop.example/api/v1/stock/42' \
  -H "Authorization: Bearer $MCMS_API_KEY" \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: 1c-stock-document-1001-line-42' \
  --data '{"on_hand":25,"expected_on_hand":20,"note":"Receipt 1001"}'
```

`on_hand` and `expected_on_hand` are required integers from 0 to 2,000,000,000.
`note` is optional, up to 150 characters. Unknown fields are rejected. The body
must be a JSON object and is limited to 16 KiB.

`expected_on_hand` must equal the current physical count under a database lock.
A mismatch or new count below existing reservations returns 409 `stock_conflict`
with `error.details.current`. Refresh inventory, resolve the accounting difference
and issue a new operation key. The API preserves reservations and audits changes;
it does not change order status. Do not send available stock as physical stock.

`Idempotency-Key` is required: 1–128 ASCII characters, first alphanumeric, then
letters/numbers or `._:-`. Keys are case-sensitive and scoped to the API credential.
Keep the same key and semantic body when retrying after timeout. A successful
receipt is stored in the same transaction as the correction. Retrying returns
that original receipt even if an order has since consumed stock; always GET the
current stock if needed. Reusing a key with another variant/count/note returns
409 `idempotency_conflict`. Failed operations do not consume their key. Receipts
are retained without automatic pruning; rotating credentials changes their scope.

## Pagination and order polling

`GET /stock` and `GET /orders` accept `after_id` (default 0), `limit` (1–200,
default 100) and optional `until_id`. Data is
`{"items": [...], "next_cursor": 42, "until_id": 100, "has_more": true}`.
IDs are ascending. Fix the first response's `until_id` for all pages of a sweep,
use `next_cursor` as the next `after_id`, and stop when `has_more` is false.
New IDs beyond the upper bound wait for the next sweep. This is a bounded live
feed, not an immutable database snapshot.

Orders additionally accept `status` (`new`, `processing`, `ready`, `completed`,
`cancelled`) and inclusive `updated_since`, encoded as `YYYY-MM-DD HH:MM:SS` in
the site's timezone. For example:

```text
GET /api/v1/orders?after_id=0&limit=100&updated_since=2026-10-09%2012%3A00%3A00
```

For repeat polling, start each sweep with `after_id=0`, so changes to older
orders are discovered. Use the **first page's request-start `meta.server_time`**
as the next successful sweep's inclusive `updated_since`, only after all pages
have been processed. Keep the same previous watermark throughout the current
sweep. Upsert/deduplicate orders by `id`; same-second changes intentionally repeat.
A failed sweep retries with the previous watermark. Use a time overlap (for
example at least one minute) and periodic full reconciliation: a transaction
may stamp an order before the sweep and commit after it was read. This feed
does not expose a commit sequence. Clock/timezone/DST changes also require an
overlap or full reconciliation. This is
current saved state, not a durable event log or every intermediate transition.
Read inventory through full periodic sweeps; it has no delta/deletion feed.

## Errors

Errors use `{"error":{"code":"...","message":"...","details":...}}`;
`details` may be omitted. Clients should branch on HTTP status and stable code.

| HTTP | Code / meaning |
| --- | --- |
| 400 | `invalid_json` |
| 401 | `unauthorized`: missing/expired/revoked key or unavailable owner |
| 403 | `forbidden`: scope/current owner permission insufficient |
| 404 | `not_found`: missing variant/order; disabled Api routes also return 404 |
| 409 | `stock_conflict` or `idempotency_conflict` |
| 413 | `request_too_large` |
| 415 | `unsupported_media_type` |
| 422 | `invalid_request`: body/filter/cursor validation |
| 503 | `module_unavailable` or `maintenance` (Retry-After during module recovery) |
| 500 | `internal_error`: correction/receipt transaction is rolled back |

CodeIgniter/nginx responses for unknown routes or unsupported methods are outside
this endpoint error contract. Retry temporary transport/500/503 errors using the
same operation key. Avoid retrying authentication or validation errors unchanged.
