# SEO

Optional sitemap and moved-resource redirects, without dependencies on particular
content modules or their private tables.

- `/sitemap.xml` includes Home and currently public resources supplied by enabled
  `PublicResourceProviderInterface` implementations. Resource enumeration is
  paginated by content providers and is not limited to the first 100 search results.
- Pages, News, Galleries and Feedback implement that optional Core navigation
  contract. Drafts, scheduled News and disabled modules are excluded.
- While SEO is enabled, `mcms.resource.moved` records the previous address against
  the provider and stable resource ID. GET/HEAD requests receive a 301 to that
  resource's current public URL, so repeated renames do not create redirect chains.
- Reused live URLs take precedence. Deleted, draft, scheduled or disabled targets
  do not redirect. Existing references remain stored and can recover on publication
  or module re-enable.
- Administration lists and deletes old-address records under `seo.manage`.

SEO owns `seo_redirects`. Disable preserves records and removes runtime redirect
filters, event listeners, public routes and administration contributions on the
next request. Changes made while SEO is disabled do not generate new records.

The current sitemap is a single document. Sitemap indexes for very large sites,
manual arbitrary redirects, mail integrations and commerce are separate future work.
