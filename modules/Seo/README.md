# SEO

Optional sitemap and moved-resource redirects, without dependencies on particular
content modules or their private tables.

- `/sitemap.xml` includes Home and currently public resources supplied by enabled
  `PublicResourceProviderInterface` implementations. Resource enumeration is
  paginated by content providers and is not limited to the first 100 search results.
- Pages, News, Galleries and Feedback implement that optional Core navigation
  contract. Drafts, scheduled News and disabled modules are excluded.
- Pages, News and Galleries supply their content modification timestamps through
  that contract. Sitemap emits W3C `lastmod` with the application timezone,
  including the selected published Home page. Routes without a known content
  modification date omit `lastmod`; generation time is never substituted.
- While SEO is enabled, `mcms.resource.moved` records the previous address against
  the provider and stable resource ID. GET/HEAD requests receive a 301 to that
  resource's current public URL, so repeated renames do not create redirect chains.
- Reused live URLs take precedence. Deleted, draft, scheduled or disabled targets
  do not redirect. Existing references remain stored and can recover on publication
  or module re-enable.
- Administration creates, edits, lists and deletes redirects under `seo.manage`.
  Manual sources and targets are local site paths (up to 500 ASCII characters,
  without a domain, query or fragment); legacy `.html` paths are supported.
  A published target is bound to its provider/resource ID and follows subsequent
  renames and publication state. Other local targets, including `/`, are stored
  as paths; their existence is the administrator's responsibility.
- Duplicate sources, live/system sources, self-redirects and cycles are rejected.
  Manual chains resolve to their final target in one 301; imported cycles do not
  redirect. Existing live URLs retain priority over saved redirects.

SEO owns `seo_redirects`. Disable preserves records and removes runtime redirect
filters, event listeners, public routes and administration contributions on the
next request. Changes made while SEO is disabled do not generate new records.

SEO 0.3.0 adds a nullable `target_path` column through a module migration;
`php spark mcms:update` upgrades existing enabled installations (also run at
container startup). Existing automatic records are preserved. Rolling this
migration back removes path-only manual records and preserves resource-backed
records.

The current sitemap is a single document. Sitemap indexes for very large sites,
external/query-based redirects, mail integrations and commerce are separate future work.
