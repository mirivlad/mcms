# Order notifications

Notifications 0.1.0 is an optional module depending on Orders >=0.3.2 <0.4 and Core
^0.1.6. It has no public routes. `notifications.manage` controls all settings,
test-email and retry actions; every write uses the site's session/CSRF protection.

## Configure

Enable Notifications in Modules. Commerce → Order notifications and Settings →
Order notifications are two entry points to the same settings and queue.

- Notifications are off by default; save one manager email address before enabling.
- Select order creation (`new`) and/or processing, ready, completed, cancelled.
- Edit the subject and plain-text message. Available placeholders:
  `{order_id}`, `{status}`, `{customer_name}`, `{phone}`, `{total}`, `{delivery}`,
  `{items}`, `{site_name}`. Unknown placeholders are rejected before saving.
- Save first, then send a test email to the saved recipient. The test does not
  create an order or require automatic notifications to be on.

No customer email is used as a recipient. Totals include delivery and the saved
currency; products, variants and delivery names come from the order snapshot.
The notification stores its rendered message and recipient at the event, so later
template/recipient/product edits do not change a waiting email.

## SMTP

The module shares `Config\Email` with site mail. The settings screen shows the
current sender and SMTP host/port; passwords are not exposed. In the standard
Compose stack, the app sends to `mail:587`. Configure the relay through
`MCMS_SMTP_RELAYHOST`, `MCMS_SMTP_RELAY_USER`, `MCMS_SMTP_RELAY_PASSWORD` and the
sender through `MCMS_MAIL_FROM` / `MCMS_MAIL_FROM_NAME`. These are server environment
values, not application settings. A saved recipient alone does not configure a
working external relay; use the test button to verify the actual server setup.

## Queue and retries

Orders owns `Api\OrderEvents`: `RECORDED(orderId, status, historyId)` runs inside
the order transaction. Listeners must write only local state on the same database
connection, without network I/O or another transaction. Notifications inserts an
outbox row with a unique history ID. A rollback removes both the order change and
its queued email. An outbox persistence failure aborts the order transaction.

`COMMITTED(orderId)` runs after the outer transaction commits. Notifications then
attempts one waiting email for that order. SMTP failure cannot undo the order,
stock reservation, terminal transition or cart clear; the email stays waiting
with its attempt count and error. Same-status transitions do not produce events.
For callers wrapping Orders in an outer transaction, automatic sending is skipped;
after committing they must drain the queue through the worker.

Recent delivery states are visible in administration. **Retry waiting emails**
processes up to 20 waiting rows. Operators can run `php spark notifications:send`
to process up to 100 rows, and invoke it periodically with their existing scheduler.
No background scheduler is installed by the module. Turning notifications off
pauses both new queue entries and retries. Disabling the module removes its routes
and listeners; re-enabling preserves preferences and queue records.

Senders atomically claim rows, so simultaneous workers cannot send the same live
claim. Claims abandoned by a crashed process become retryable after at least five
minutes (longer if SMTPTimeout exceeds thirty seconds; the default is ten seconds).
Delivery is **at least once**: if SMTP
accepts mail immediately before the application crashes, a later retry can send a
duplicate. SMTP does not offer an exactly-once acknowledgement contract.

The HTTP/SMTP smoke uses a loopback-only receiver in a disposable container. It
checks a rejected SMTP DATA command, successful order creation/reservation,
retry, real received headers/body, selected/unselected events, no-op transitions,
CLI, permissions and disable/re-enable persistence. It never relays external mail.
