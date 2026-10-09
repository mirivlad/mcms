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
