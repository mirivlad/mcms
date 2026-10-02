# Feedback

Independent contact form and administration inbox; no content-module dependency.

- Public `/contact` form with name, email and plain-text message.
- CSRF protection, honeypot and a five-submissions-per-minute IP throttle.
- Name/email/message validation and a 5000-character message limit.
- Paginated inbox, message detail, explicit mark-read and deletion actions.
- Separate `feedback.view`, `feedback.manage` and `feedback.delete` permissions.
- Logical `feedback.form` theme template with a module fallback.
- Generic `feedback.contact` navigation resource and `feedback.repository` API.

Messages are stored in `feedback_messages`; disabling Feedback preserves them.
This version delivers messages to the administration inbox. It does not send
email or expose messages through a public API.
