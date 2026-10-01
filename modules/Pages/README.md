# Pages

First-party reusable static-pages module for mCMS.

## Features

- draft and published page states;
- root-level public URLs such as `/about`;
- automatic Russian/English title-to-slug transliteration;
- SEO title and description;
- safe HTML sanitization;
- lightweight rich-text editor;
- image insertion through the public `media.library` module service;
- English and Russian administration UI;
- public repository service exposed as `pages.repository`;
- generic internal-link provider exposed to Core navigation as `pages.page`.

## Dependency

Pages requires Media `^0.1`. Enabling Pages through Core Module Manager
automatically enables Media first.

## Data ownership

Pages owns the `pages` table. Disabling the module removes its routes and
admin contribution but preserves page rows. Re-enabling restores them.

## Public routing

Published pages are available directly by slug:

```text
/about
/contacts
/delivery
```

Core and Shield routes are registered before Pages, and reserved top-level
slugs such as `admin`, `login`, `register`, `install`, and `auth`
cannot be assigned to a page.
