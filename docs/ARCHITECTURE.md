# mCMS architecture

mCMS is a standalone CodeIgniter 4 application.

## Principles

1. A fresh installation must work against an empty database.
2. Database structure is defined by migrations.
3. Demo content is synthetic and optional.
4. Business-specific integrations are adapters, not core assumptions.
5. Uploaded files are data and can never execute as PHP.
6. Deployment configuration contains no customer-specific hostnames, identifiers or secrets.

## Functional areas

- Core: configuration, settings, health and administration shell.
- Identity: users, authentication, roles and permissions.
- Content: pages, news, categories and navigation.
- Media: uploads and galleries.
- Commerce: catalogue, cart, stock, orders and delivery.
- Integrations: optional delivery, mail and other providers.
