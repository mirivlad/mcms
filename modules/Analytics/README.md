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

## Reports (0.2.0)

- Compare against the immediately preceding period of equal calendar-day length.
  Both periods share the selected filters. Percentage changes have no numeric
  value when the baseline is zero and the current value is nonzero. Comparison
  is unavailable if any part of the preceding period is outside retention;
  expired history is never represented as zero traffic. A current day may still
  be incomplete, and missing recorded traffic does not establish site inactivity.
- Filter by initial referral hostname (including direct traffic), initial device
  and a path section. `/news` includes `/news` and `/news/...`, but not
  `/newsletter`; `%` and `_` are literal characters. Filters apply to totals,
  tables, both chart periods and page details.
- Switch between daily chronology and the 24 hours of day. Hourly values combine
  matching UTC hours across the entire selected period. Visitors and visits are
  distinct within each bucket; bucket unique counts must not be added together.
- Open a page from the popular pages table for its own chart, sources, devices,
  entrances and exits. Titles come from currently public navigation providers;
  removed/unavailable resources fall back to their saved URL path. Titles are
  current, not historical snapshots. Entrances/exits use the earliest/latest
  retained view of the entire visit (time, then ID), before filtering by page or
  date. Active visits may continue. The first page remaining after retention
  cleanup is the first observable page. Repeated views of a page still count as
  views; page-level depth means views of that page per matching visit.

This version uses the existing schema and collection flow. It adds no UTM,
conversion events, browser tracker, long-term aggregates or new retention policy.

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
