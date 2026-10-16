# Analytics

Optional first-party traffic analytics, stored entirely in the mCMS database.
Enable **Analytics** in Administration → Extensions → Modules. Its standalone
sidebar link opens `/admin/analytics` for users with `analytics.view`.

The dashboard includes page views, visits, distinct visitors, pages per visit,
daily SVG charts with metric toggles, automatic scaling, per-day numbers and an accessible data table, top 20 paths and referral hosts,
and device shares. Choose 7/30/90 days or an inclusive custom range up to 90 days.
All timestamps and date boundaries use UTC. No external scripts or chart libraries
are loaded. Period totals deduplicate visitors and visits across days, rather
than summing daily unique counts.

## Collection and identifiers

An enabled module registers an HTTP after-filter. It counts successful GET HTML
responses, independently of the public theme. Reloads count as views. There is no
public ingestion endpoint and no client-side code to insert into themes.

Two first-party, HttpOnly, SameSite=Lax cookies are set on tracked responses,
scoped to the configured application base path, with the application's Secure
policy. `mcms_analytics_visitor` contains a random 128-bit browser token with a
sliding one-year expiry. `mcms_analytics_visit` contains a random 128-bit visit
token, expiring after 30 minutes of inactivity. The server also checks the saved
last-seen time before reusing a visit. Only SHA-256 token hashes enter the DB;
no account ID, IP address or full User-Agent is retained. Visitors represent
browsers, not verified people. Without cookies, each view starts a new visitor
and visit. Explain these cookies in the site's public privacy information.

Only URL paths (up to 500 bytes), UTC times, coarse device class and the initial
external referrer hostname are saved. Queries, fragments, referrer credentials
and referrer paths are discarded. Internal/missing referrers share the direct
traffic bucket. Device and source attribution remain fixed for a visit.

Excluded: non-HTML, errors, redirects, HEAD/POST, AJAX, prefetch, empty/known bot
User-Agents, DNT=1 or Sec-GPC=1, administration, authentication, account, install,
health, assets, API, CAPTCHA, cart and checkout paths. Bot detection is heuristic;
server-side analytics cannot distinguish every bot or see pages served entirely
from an external cache. Framework full-page cache hits which bypass module boot
also bypass collection; full-page caching is currently off by default in mCMS.
Recording failures are logged and do not break public responses.
Tracked responses carry `Cache-Control: private, no-store` so shared HTTP caches
do not reuse visitor cookies for other browsers.

## Data lifecycle

Analytics owns `analytics_visits`, `analytics_pageviews` and `analytics_state`.
Rows outside the last 90 UTC calendar days are pruned lazily on the first tracked
request or report request each day. While the module is disabled no collection
or cleanup runs; existing data is preserved until re-enabled. Module migrations
handle schema creation/rollback. Standard mCMS backup includes these tables.
