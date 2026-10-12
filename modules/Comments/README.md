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

A native notification adapter, per-resource editing controls, more sophisticated
anti-abuse moderation, a nonvisual CAPTCHA alternative, and improved threaded
reply UX remain planned. The comment table does not own its source records:
deleted resources leave safely hidden comments until an explicit cleanup policy.
