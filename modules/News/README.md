# News module

News provides dated public articles independently from Pages.

## Public model

- archive: `/news`;
- article: `/news/{slug}`;
- draft or published state;
- optional explicit publication time;
- future publication times are treated as scheduled and stay private until due;
- excerpt, sanitized rich-text body and SEO metadata;
- optional featured image from the Media module.

The module requires Media ^0.2 but does not depend on Pages. Both Pages and News use
Core's shared `HtmlSanitizer`.

## Administration

- searchable/filterable article list with pagination;
- Pages-style rich-text editor controls;
- searchable Media picker for featured images and inline article images;
- collapsible SEO metadata panel.

## Theme contract

News renders through the active theme using:

- `news.index` for the archive;
- `news.article` for an individual article.

Both have module-owned fallback views. The built-in `mcms-default` theme
implements both logical templates.

## Navigation

News registers two generic Core navigation providers:

- `news.archive` with resource `index` for the archive;
- `news.article` for currently published articles.

Menus consumes these through `NavigableResourceRegistry`; there is no direct
Menus → News dependency.

Both providers support complete public-resource enumeration for the optional
SEO sitemap. Slug changes emit `mcms.resource.moved` with a stable article ID.

## Services

The module exposes `news.repository` implementing
`MCMS\Modules\News\Api\NewsRepositoryInterface`.

A read-only `media.usage` data provider reports inline and featured-image
references from all articles, including drafts and disabled-module data.
