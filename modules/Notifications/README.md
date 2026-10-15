# Notifications 0.1.0

Optional manager emails for order creation and selected status changes. Requires
Orders 0.3.2 and Core 0.1.6. Enable it in Modules, then configure **Order
notifications** from Commerce or its section in the Settings centre. Both entry
points share the same handlers and saved values.

Delivery is off by default. Save one manager email address, select events and edit
the plain-text subject/body templates. Use **Send test email** after saving the
recipient. Tests work while notifications are off; customers are never used as
recipients. Mail transport uses the existing site Email configuration.

See [notification delivery and operations](../../docs/NOTIFICATIONS.md) for queue
semantics, retries and SMTP configuration.


## Comment notifications (0.2)

The same module also accepts optional Comments events without requiring Comments
to be installed. Configure a separate recipient and pending/published events on
the existing Notifications screen. This switch is independent of order delivery.
`notifications_messages` retains immutable text snapshots with unique event keys;
manual Retry and `notifications:send` handle both queues. Messages are recorded in
the comment transaction and sent after commit. A live Core event-source interface
cancels stale public alerts after rejection/deletion/unpublication; disabled sources
keep their queue waiting. No customer or comment-author email becomes a recipient.
