# Comments module

Enable **Comments** in Admin → Modules. It automatically enables **Captcha** as a dependency.
Comments only operate on **published resources** registered by Core's navigation registry,
addressed by stable \`target_type + target_id\` rather than a copied URL.
By default, Pages, News articles and Catalogue products are enabled. The source
modules can be disabled without deleting comments; any unavailable source stops
accepting new comments.

## Public functionality

The four bundled themes display comments under published Pages, News articles and
Catalogue products. A custom theme can include the widget inside a resource view:

    <?php if (service('mcmsModuleServices')->has('comments.repository')): ?>
        <?= view('MCMS\\Modules\\Comments\\Views\\widget', [
            'targetType' => 'pages.page', 'targetId' => (string) $page['id']
        ]) ?>
    <?php endif ?>

The widget is never displayed in unsaved draft previews. Users may submit replies
(to depth 3 by default). Existing published comments are escaped as plain text,
never rendered as HTML. Guests supply a name and optionally email (never shown).
Guest submissions require a first-party session-bound one-time captcha. All
submissions are subject to per-IP throttles and CSRF validation. All comments are
pending until a moderator publishes them, unless the site owner opts into
auto-approval of registered users.

Comment reports require a reason and are unique per reporting session/account.
Reports are visible in the moderation queue. Removing a comment tombstones its
body, author and email to preserve descendants' ancestry without retaining
personal data.

## Administration

- Moderation queue at \`/admin/comments\`, filtered by pending/published/rejected/spam.
- Publish, reject, flag as spam, or delete comments with separate permissions.
- Settings at \`/admin/comments/settings\`: guest commenting, auto-approval,
  maximum reply depth and enabled target types.
- Disabling Comments keeps tables and data.

## Known follow-ups

Further anti-abuse moderation remains planned. The comment table does not own its source records:
deleted resources leave safely hidden comments until an explicit cleanup policy.

## Material policies, threads and notifications

Pages, News and Catalogue editors expose independent inherit/allow/deny controls
for Comments and Ratings to users allowed to create/edit that material. The Core
`ResourceEditorExtensionInterface` discovers optional controls through the service
registry. Owners save them in the same transaction as content; Comments owns
`comments_resource_options`. Missing controls and disabled modules preserve prior
choices. Explicit allow overrides the global type switch, but never makes drafts,
future news, deleted resources or disabled source modules public. Direct comment
and report endpoints enforce the same policy as rendering.

Replies are ordered with their parents and show parent links and reply buttons.
Indentation is capped on phones; rejected ancestors are not exposed. Tombstones,
reports, moderation and depth limits remain in effect.

Notifications → Comment notifications has an independent recipient and selected
pending/publication events. Comments emits transaction-local `mcms.comments.recorded`
and post-commit `mcms.comments.committed` events, without depending on Notifications.
Pending messages are explicitly moderation alerts and point to the admin queue.
Published alerts link to the public comment. Delayed mail checks the module's
`NotificationEventSourceInterface` before delivery: withdrawn/hidden comments cancel
the message; a disabled provider leaves it queued. Failed SMTP does not undo the
comment. Delivery is at least once; public re-publication is deduplicated per comment.
