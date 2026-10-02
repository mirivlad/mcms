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

Pages requires Media `^0.2`. Enabling Pages through Core Module Manager
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

Fixed prefixes declared by installed code, including disabled News/Galleries/
Feedback modules, are reserved too. Enablement detects legacy conflicts and asks
for explicit renaming. Page settings can choose any published page as Home.
The selection survives module disablement; draft/deleted pages use the default Home.
Pages exports Media usage references and emits `mcms.resource.moved` on slug changes.

## Editor workflow and search

The editor supports manual SEO title/description/keywords, tags, private unsaved
preview, per-user autosaved drafts and revision restoration into the form. Core
SEO rules can derive keywords from these tags. Pages owns pages_drafts and
pages_revisions alongside pages. The pages.search service contributes only
published pages to Core public search at `/search/results`; a page at `/search`
keeps its existing URL. Disabling Pages preserves content and workflow tables.
