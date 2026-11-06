# Implementation plan

For engineers. Every path and behaviour named under "Today" was checked against the code on
7 October 2026. Everything under "Build" is proposed and does not exist yet. Table and class names
are suggestions; rename them freely, but keep the boundaries.

## Ground rules

- **Each data source writes its own tables.** Search Console, crawl, SERP and backlink data do not
  go into `website_time_series_records` or `webpage_time_series_records`. Those are rebuilt from
  `website_visitors`, which is pruned after 30 days, and `UpsertsTimeSeriesRecords::syncTimeSeriesRecords()`
  deletes periods it does not rebuild. Search Console keeps 16 months and providers charge per
  request, so our copy is the long-term copy and must never be on a delete path.
- **Fetch jobs run on `long-low-priority`**, the long-running queue the time series redo jobs use, so a
  slow provider cannot hold up `analytics`. A dedicated `seo` queue would need a Horizon supervisor in
  every environment; add one only if fetches start to crowd that queue.
- **Every fetch is idempotent per day.** Unique keys include the date, and a re-run upserts.
- **Provider calls go through one client per provider**, with the API key in `config/services.php`
  and a log row per request in `seo_api_requests` (provider, endpoint, rows, duration, error, cost
  if the provider reports it). Phase 2 and 3 costs are only visible this way.
  `App\Services\SearchConsole\SearchConsoleClient` is the first one.
- **One monthly budget for all paid SEO APIs**, not one per provider or feature: DataForSEO and the
  AI gateway calls of content help count against the same cap (default 250 USD,
  set on the [API usage page](#api-usage), `SEO_API_MONTHLY_BUDGET` as the default).
  `App\Services\SeoApi\SeoApiBudget` adds up the month's cost in `seo_api_requests`; every client
  checks it before a billable call. Reading results already paid for is never blocked. Feature
  screens do not show the spend; the API usage page does.
- **Visitors are filtered by channel per platform (HELP-4062).** The Channel filter of the Visitors
  tab lists Organic Google, Google Ads, Organic Bing, Bing Ads, Organic Meta, Meta Ads and the other
  organic and paid sources one by one (only those with visitors), then Email, AI, Direct, Internal
  and Other, so Google is never mixed with Bing or Meta.
- **The SEO dashboard stays the entry point.** Its tabs, at the top like those of a website: Overview
  (the cards filtered by the dashboard interval and the table tabs Top pages, Conversions, Search
  queries, Low CTR queries and Page speed, switched with the `table` query parameter), Visitors,
  Page views and Missing pages. Only the open table is built. The SEO menu holds the tools with tabs
  of their own: Site audit, Keywords, Backlinks and Competitors; the old Visitors, Page views and
  Missing pages addresses open their tab. What covers all shops lives at group level, in the
  Website menu: SEO portfolio and API usage, linked from the heading of every SEO dashboard for
  users with group access.

## Phase 0: fix the data we already show

Done on 7 October 2026.

- Mobile sessions: `ProcessWebsiteTimeSeriesRecords` counts `Smartphone` and `Phablet` (and
  `mobile`) as mobile. Before, it only matched `mobile`, which `GetBrowserInfo` never stores.
- Session duration: `UpdateWebsiteVisitor` adds the time since the previous hit, capped at
  30 minutes (`UpdateWebsiteVisitor::MAX_IDLE_SECONDS`), instead of `last_seen_at - first_seen_at`.
  This matches the 30 minute cap `StoreWebsitePageView` already applies per page view.
- `maintenance:recalculate_website_visitor_durations {website?}` rewrites the duration of the
  visitors still stored from the sum of their page view durations. Run it once after deploying,
  then `websites:redo_time_series --from=<30 days ago> --to=<today>`. Older days cannot be
  corrected because their visitor rows are gone.
- The `pagespeed` and `pagespeed_history` props on the webpage and website pages are now
  `real_user_speed` and `real_user_speed_history`, because they carry CrUX and Web Vitals data.

## Phase 1: our own sites

### 1.1 Search Console history

Done on 7 October 2026.

**Credential.** The service account JSON, base64 encoded, in the group settings
(`gcp.oauthClientSecret`) or `config('app.analytics.google.client_oauth_secret')`, with the
`WEBMASTERS_READONLY` scope. The service account has to be a user on each Search Console property.

**Fetch.** `FetchSearchConsoleAnalytics` (`app/Actions/Web/SearchConsole/`), command
`search_console:fetch {website?} {--from=} {--to=} {--async}`, scheduled daily at 02:30 UTC with
`--async`.

- `SearchConsoleClient::forWebsite()` reads `website.data.gcp.siteUrl`. When it is missing, it lists
  the properties the service account can see and saves the first exact match for the domain
  (`sc-domain:`, then `https://www.`, `https://`, `http://www.`, `http://`). A website without a
  match is skipped.
- A first run backfills 16 months. Later runs start 3 days before the last stored day, because
  Google finalises data with a delay, and stop yesterday. Only final data is stored
  (`dataState=final`).
- Per day, three requests, each paged with `startRow` in pages of 25,000 rows:
  - `date, country, device` into `search_console_website_days`: website totals. When this returns
    nothing the day has no data yet, and the other two requests are skipped.
  - `date, page` into `search_console_page_days`: page totals, including the clicks of anonymised
    queries that the page and query rows leave out.
  - `date, page, query` into `search_console_page_queries`.
- Every row is upserted on (website, date, hash), so re-runs are safe.
- `page_url` is matched to `webpages` by the path of the canonical URL or by `url`, live pages
  first. Unmatched URLs are kept with a null `webpage_id`.
- Volume: the largest site (ancientwisdom.biz) had about 11,000 page and query rows a day in 2025
  and about 1,700 in October 2026. A row takes about 300 bytes with its indexes, so 16 months of all
  websites is a few GB. The tables are not partitioned yet; partition `search_console_page_queries`
  by month on `date` if queries on it slow down or old months need dropping.

**Screens.**

- SEO dashboard: a Google Search card with clicks, impressions, CTR, average position and a daily
  chart for the dashboard interval. When the website has no readable property, the card names the
  service account to add instead.
- Tabs under the cards:
  - Webpages: search clicks, impressions and position added beside the traffic columns, from
    `search_console_page_days`.
  - Search queries: by query, with clicks, impressions, CTR, position, the page Google showed most (up to five are kept, the rest counted) and the number of pages shown.
  - Low CTR queries: at least 100 impressions, average position 10 or better, CTR under 2%
    (constants on `IndexSearchConsoleQueries`).
- Position is always weighted by impressions.
- The webpage Performance tab (and the blog webpage one) reads `search_console_page_days` instead of
  calling the API. `GetWebpageGoogleCloud` and `GetWebsiteGoogleCloud` are deleted.

### 1.2 SEO crawler (Site Audit)

Done on 7 October 2026.

**Crawl.** `AuditWebsite` (`app/Actions/Web/Crawl/`), command
`crawl:audit {website?} {--max-pages=} {--concurrency=2} {--async}`, scheduled every Sunday at
03:00 UTC for live websites. A "Run audit now" button on the Site Audit page starts one through
`StartSiteAudit` (`grp.models.website.site_audit.store`).

- Audits are rows in `crawls` with type `audit`; the cache warmer is unchanged. An audit claims
  concurrency from the same budget of 8 the cache warmer uses, and stopping works the same way
  (`should_stop`).
- It always audits the public site: the scheme and host of the storefront's canonical URL, or
  `https://` and the domain. `Website::getUrl()` points at local hosts outside production.
- It reads `robots.txt` (its own parser, longest match wins, `*` and `$` supported), reads every
  sitemap listed there or `/sitemap.xml`, starts from the home page and the sitemap URLs, and follows
  internal links that are not `nofollow`. Links with a query string and links to files (images,
  PDFs, scripts) are not followed.
- User agent `Mozilla/5.0 (compatible; AikuSiteAuditBot/1.0)`. Since 6 Nov 2026 Varnish does not treat
  it as a bot (the exclusion list beside `aiku-cache-warmer` in `default.vcl`), so it gets the copy people
  and Google get and the Cloudflare edge answers most of its pages without a render; what it does render
  warms the cache for people. Response times are therefore what a visitor gets, edge included.
- Every request carries `X-Aiku-Site-Audit: <crawl id>`. `TagSentryWithSiteAudit` (first in the `iris`
  middleware group) tags the Sentry events of that request `site_audit`, and 5xx and "rendered without
  its content" issues link to Sentry filtered by audit and URL (`services.sentry_issues_url`).
- Pacing: at most one page every 250 ms per fetch slot (`MIN_MS_PER_PAGE`). Pages that failed or
  answered 5xx are asked once more after every other page before they are reported.
- Redirects are not followed by the HTTP client. Each hop is its own row, so chains and loops are
  visible.
- HTML is parsed with PHP 8.4 `Dom\HTMLDocument`. No package was added.
- No page limit: an audit runs in parts of 1,000 pages or 15 minutes queued 30 seconds apart, with its
  progress in the cache. A part that throws is retried twice from the last saved part (`jobTries` 3, pages
  are upserted). An audit that still stops early (failed, abandoned, stopped by a user) gets the report
  of the pages it read (`ReportSiteAudit`), and the Site audit page says why it stopped. An audit whose
  robots.txt keeps it out of every page finishes as `blocked_by_robots`.
- After a completed audit, `ReviewSiteAuditPagesWithAi` has Claude Opus read the home page and the 20
  pages with the most Google clicks in the last 28 days (their text, title, description, h1, the
  searches they appear for and the audit's issues) and keeps a score, a summary and findings on the
  page row (`crawl_pages.ai_review`). It is paid from the SEO API budget and logged in
  `seo_api_requests` (`site_audit_ai_review`). Shown on the Site audit page, weakest first.
- The last 10 audits per website are kept; older ones are deleted with their pages and issues.

**Tables.** `crawl_pages` (one row per URL per audit, with status, redirect target and hops,
response time, content type, title, meta description, canonical, robots meta, h1 count, images
without alt, internal links in, in sitemap, indexable, matched `webpage_id`) and `crawl_issues`
(crawl, page, type, severity, details). `crawls` gained `max_pages`, `health_score`,
`pages_with_errors` and a count per severity.

**Issues** (`CrawlIssueTypeEnum`, thresholds are constants on the enum):

- Errors: 4xx, 5xx, fetch failed, redirect loop, missing title, canonical pointing at a URL that does
  not answer 200.
- Warnings: redirect chain, duplicate title, duplicate meta description, missing meta description,
  missing or multiple h1, missing canonical, noindex page in the sitemap, response over 3 s, images
  without alt.
- Notices: title over 60 or under 20 characters, meta description over 160 or under 70, canonical
  pointing at another page, indexable page missing from the sitemap, internal links pointing at a
  redirect.
- Content checks (title, description, h1, duplicates, sitemap) only run on indexable pages. Broken
  pages carry up to 10 of the pages linking to them.

**Health score** is the share of crawled pages with no error-level issue, stored on the crawl row.

**Screens.** SEO > Site audit: health score with the change since the previous audit, counts per
severity, the trend over the kept audits, and the issue list with pages per issue and the change
since the previous audit. Each issue opens a page list with the URL, the matched webpage in Aiku,
status, internal links in and the issue details. While an audit runs, the page polls its progress
every 5 seconds. An Excel button downloads the latest audit as a workbook: the audit in numbers, the
issue types against the previous audit, and one row per page and issue. "Pages read" (the coverage
line) lists every page of the latest audit with status, title, description, h1, indexable, clicks from
home, links in, response time, words and issues. An issue page's "Ask webmasters to fix" opens one staff
task per website and issue type in the webmaster department; asking again posts in it, and the first
completed audit that no longer finds the issue closes it.

**First result.** A 150 page test audit of awgifts.bg found product pages rendering
`<meta name="description" content>` with no text, because `webpages.description` is empty while
the product has a description. Phase 3.4 (content help) is the natural place to fill these.

**External links.** `external_links:check_status` (`RecheckExternalLinkStatuses`) rechecks every
external link weekly, Sunday 02:00 UTC, five at a time.

### 1.3 404 log

Done on 7 October 2026.

- `ShowIrisWebpage` dispatches `RecordWebsiteNotFoundHit` (queue `analytics`) just before the
  `abort(404)` for a path with no webpage and no redirect, with the website, the path, the referrer
  and the user agent. The other two 404s there (exclusive products, webpages that fail to render)
  are not logged: those pages exist and need a different fix.
- The job skips bots with `IsBot` and upserts one row per path in `website_not_found_paths`
  (website, path, last segment, hits, first and last seen, last referrer, ignored). A trailing
  slash does not make a new row.
- `maintenance:prune_website_not_found_paths` deletes paths not seen for 90 days, daily at 03:50 UTC.
- Hits are a floor, not a count: when Varnish caches a 404, repeat visits never reach Laravel.

**Screen.** The Missing pages tab of the SEO dashboard lists paths by hits with last seen, first seen and where the last
visit came from, filtered to Open by default (Open, Fixed, Ignored).

- "Create redirect" opens a modal to pick a live page and posts to the existing
  `StoreRedirectFromWebsite` (`grp.models.website.redirect.store`).
- Redirects in Aiku match on the last path segment, so the modal says that every URL ending the same
  way goes to the chosen page.
- A path counts as Fixed while a redirect exists for its last segment or a live webpage now uses it.
  This is computed, not stored, so redirects made elsewhere also count.
- "Ignore" hides probes such as `/wp-login.php`; "Restore" brings them back.

### 1.4 Page speed (field data, following HELP-3303)

Done on 7 October 2026, without lab scores.

HELP-3303 (commit `92f37c381f`, 24 September 2026) replaced the Lighthouse lab test with field data:
the lab test scored pages such as the stationery family at 49 while real desktop visitors passed, so
staff chased a number customers never saw. 1.4 follows it and adds no PageSpeed Insights calls.

**Today (from HELP-3303).** `FetchCruxRecords` (`crux:fetch`, Tuesdays 01:00 UTC) stores the Chrome
UX Report history of every live website and of every page with 50 or more views in 28 days in
`crux_records`. `StoreWebVitalSample` records LCP, INP, CLS, FCP and TTFB from visitors' browsers
in `web_vital_samples` (kept 400 days). Both show as Real user speed on the website and webpage
pages.

**Built.**

- SEO dashboard, Page speed card: the website's 75th percentile LCP, INP and CLS for mobile and
  desktop, with a Good, Needs improvement or Poor mark per metric and a Passes or Fails Core Web
  Vitals verdict per device. A toggle switches between Google (the latest Chrome UX Report period)
  and Our visitors (last 28 days). Not affected by the dashboard interval. Built by
  `GetWebsitePageSpeedSummary`.
- SEO dashboard, Page speed tab (`IndexWebpagesPageSpeed`): every webpage with at least 5 measured
  page loads in 28 days, with its 75th percentile LCP, INP and CLS and the worst of the three as
  the Core Web Vitals status, worst pages first. Filter by Mobile or Desktop. The code opens the
  webpage's Performance tab, where the weekly history is.
- Limits are Google's: LCP 2.5 s and 4 s, INP 200 ms and 500 ms, CLS 0.1 and 0.25.

**Configuration.** `GOOGLE_CRUX_API_KEY`, a Google Cloud API key with the Chrome UX Report API
enabled. Without it the Google side keeps the last stored weeks and stops updating; the visitors
side needs no key.

**Not built.** Lab scores from PageSpeed Insights. The `pagespeed_{desktop,mobile}_*` columns on
`webpage_time_series_records` still hold the history of the removed nightly crawl (29,428 rows for
12,646 webpages in the 7 October restore). Nothing writes or reads them; they are kept until the
owner of HELP-3303 decides to drop them.

### Phase 1 is done when

- Search Console history is stored for every website with a property, and the webpage tab reads it. (Done)
- Every website has had at least one audit, and the issue list matches a Semrush Site Audit run on
  the same site closely enough that the team trusts it. (Built; the comparison with Semrush is still
  to do.)
- 404 paths are visible and redirects can be created from them. (Done)
- Page speed: Core Web Vitals per website and per page on the SEO dashboard, from field data. (Done)

## Phase 2: keywords

2.1 and 2.2 are built. See [status.md](status.md).

### 2.1 Keyword research

Built on 8 October 2026 on DataForSEO, so it does not wait for Google Ads Basic access.

- `App\Services\DataForSeo\DataForSeoClient` is the one client for DataForSEO (basic auth with
  `DATAFORSEO_LOGIN` and `DATAFORSEO_PASSWORD`). Every call is logged in `seo_api_requests` with the
  cost DataForSEO reports, and no billable call is made once the shared SEO API budget is reached.
- `GetKeywordIdeas` (`app/Actions/Web/Seo/`) takes up to 5 seed keywords, a URL, or both, with a
  country and a language. Each seed goes to Labs `keyword_suggestions` (its own figures and the
  keywords that contain it, like Semrush's Keyword Magic Tool), with the 300 rows shared between the
  seeds, so a search costs about $0.05 for one seed and $0.10 for five. `keyword_ideas` was tried
  first and dropped: it returns keywords from the same product category, so "incense sticks" brought
  "asda kettles". A URL goes to Labs `ranked_keywords` (what the page ranks for in Google). The
  country maps to DataForSEO's location through the free `locations_and_languages` list, cached 30
  days, which also says which languages each country has data for.
- Results (top 300, seeds first) are cached 24 hours per search and upserted into `seo_keywords`:
  average and 12 monthly volumes (Google Ads data), ad competition, CPC and top of page bids in USD,
  keyword difficulty (1 to 100, DataForSEO's estimate; DataForSEO sends 0 when it has none, which is
  stored as null and shown as a dash), intent and secondary intents with `intent_source`.
- Not built yet: the monthly refresh of saved keywords, with 2.2.

**Screen.** SEO > Keywords, tab Research: keywords or a URL, country and language (defaulting to the
shop's), results with intent, volume, a 12 month trend, difficulty, CPC, the SERP features Google
shows for the keyword (AI Overview, People also ask, images, shopping and so on, as in Rankings;
from the `include_serp_info` option, at no extra cost, with the date of the snapshot, which can be
months old) and ad competition, a filter per intent. Below them, the Search Console queries of the website
that contain the same words (last 90 days). Every result has a Track action.

### Tracked keywords and competitors (set from the UI)

- `seo_tracked_keywords`: shop, keyword (lower case), country, language, device (mobile, desktop),
  check frequency (on request, monthly, weekly, daily), optional target webpage, active. Unique per
  shop, keyword, country, language and device. Adding a keyword stores one row per device, so every
  keyword is tracked on mobile and on desktop and nobody picks a device. A check costs twice as much.
  A keyword is added once for one or more countries (HELP-4031, up to 30 at a time) and gets a row
  per country and device. The language is a preference: each country is checked in it when DataForSEO
  has data for that country in it, else in the country's own language (Bulgarian in Bulgaria, Dutch
  in the Netherlands). The Tracked keywords tab lists one row per keyword, with the
  countries in a dropdown under it; changing the check frequency, pausing or removing the row applies
  to every country and device, and each country can also be changed or removed on its own. The Excel
  export has a row per keyword and country.
- `seo_competitors`: shop, domain (stored without scheme, `www.` and path), optional name.
- SEO > Keywords, tab Tracked keywords (competitors: SEO > Competitors): add, change frequency inline,
  pause, remove.
- Access (HELP-3885): every SEO page, menu entry and SEO action is limited to PPC and admins.
  Viewing (`canViewSeo`) and editing (`canEditSeo`) go to the group admin and system admin, the
  organisation admin, the shop admin, the Shop PPC role of the shop, `ppc.{organisation}` (view or
  edit), and the SEO permissions a Job Position generates: `seo.{organisation}` with `.view` and
  `.edit`, and `org-supervisor.{organisation}.seo`. The group-level SEO portfolio and API usage (`canViewGroupSeo`) go to the group
  admin and anybody holding one of those roles in any shop; changing the API budget is group admin
  only. Webmaster roles no longer open SEO. Everything is in `WithShopPpcPermissions`.
- Nothing is fetched for them yet; that is 2.2.

### 2.2 Rank tracking

Built on 9 October 2026 on DataForSEO's SERP API.

- A tracked keyword is checked On request (the default for a new keyword since 12 October 2026, as
  the marketing team asked: no automatic cost until someone wants the position), Monthly, Weekly or
  Daily.
- Check now, in the Rankings tab, queues a check for every active keyword of the shop not checked
  today and not waiting for a result, or for one keyword from its row, whatever its frequency
  (`CheckSeoKeywordsNow`, `PostSerpTasks::requested()`). The button shows the number of keywords and
  the cost, and asks first from 1 USD; pressing twice the same day queues nothing.
- `PostSerpTasks` (daily 00:30 UTC) queues a Google check for every active tracked keyword that is
  due: daily ones every day, weekly ones once 7 days have passed, monthly ones once 30 days have
  passed, On request ones never. It
  uses the standard queue
  ($0.0006 per page of 10 results), reading daily keywords to the top 20 and the others to the top
  30, as in [budget.md](budget.md). Tasks carry a tag with a hash of `APP_URL`, so environments
  sharing the DataForSEO account never collect each other's tasks. A task with no result after 72
  hours is posted again.
- `CollectSerpTasks` (every 15 minutes) reads `tasks_ready` and stores the ready results through
  `StoreSerpResult`. Reading results is free, so it is not stopped by the monthly budget; posting is.
- `seo_keyword_rankings`: one row per keyword and day: our organic position (null when not within the
  depth read), ranking URL and webpage, the SERP features shown (AI Overview, shopping, local pack,
  ...), whether the AI Overview cites our domain, and the depth read.
- `seo_competitor_rankings`: each competitor's position and URL from the same result, at no extra cost.
- `seo_tracked_keywords` keeps the latest check (position, previous position and date, ranking URL,
  SERP features, AI Overview) for the screen, and the pending task id.
- `RefreshTrackedKeywordVolumes` (daily 00:15 UTC) refreshes volume, difficulty and intent of tracked
  keywords older than 30 days through Labs `keyword_overview`, 700 keywords per request, so a keyword
  added by hand gets its figures the next day.
- Commands for a manual run: `seo:post_serp_tasks`, `seo:collect_serp_tasks`,
  `seo:refresh_keyword_volumes`.

**Screen.** SEO > Keywords, tab Rankings: a Mobile / Desktop switch, one row per keyword
showing the country where it ranks best, with the other countries in a dropdown under the
row (each with its own position, change, page, volume, SERP features, watch and check). Check now on
a row checks all its countries. The Excel export has a row per keyword, country and device.

- Visibility: share of checked keywords in the top 3, 10 and 20, how many went up or down since the
  previous check, how many AI Overviews cite us.
- By search intent: share of keywords per intent and our top 10 share within it; each intent filters
  the table.
- Competitors on the same keywords: their top 3 and top 10 share next to ours.
- Table: position, change (with New and Lost), the Search Console average position of the last 28
  days beside it, ranking page, volume, intent, SERP features and the competitors' positions.

### Phase 2 is done when

- Every shop has its tracked keyword list, checked on schedule within budget.
- Every tracked keyword has an intent, and the intent overview is on the Rankings tab.
- For a sample of keywords, positions match Semrush Position Tracking for the same location and
  device, give or take the day-to-day movement both tools show.

## Phase 3: the outside world

### 3.1 Backlinks

Built on 9 October 2026 on DataForSEO's Backlinks API ($0.024 per request plus a small amount per
row returned).

- `FetchBacklinks` (Mondays 01:00 UTC, `seo:fetch_backlinks {website?} {--backlinks}`), for every
  live website and the competitor domains of its shop, each domain once a day at most:
  - `seo_backlink_summaries`: domain, date, DataForSEO rank (0 to 100), backlinks, referring
    domains, broken backlinks and pages, spam score, and the new and lost referring domains since
    the previous week.
  - `seo_referring_domains`: domain, referring domain, rank, backlinks, DataForSEO's first seen, and
    our own first and last fetch and `lost_at`. Ours are read up to 3,000, a competitor's up to
    1,000, strongest first. Lost is only set when the list was read to the end, so a domain past the
    limit is never called lost.
- Every four weeks for our own websites, `seo_backlinks`, one row per link: one link per referring
  domain (up to 1,000), every broken link, and the links first seen and lost since the previous
  fetch, with DataForSEO's own first seen, last seen and lost dates. `target_path` is stored like
  `website_not_found_paths.path`, and `target_webpage_id` is matched like Search Console pages.
- Links and referring domains from our own websites are flagged `is_own_website`. Most of our
  referring domains and broken links come from our other shops (ancientwisdom.biz, October 2026:
  26 of its top referring domains, and broken links from the AW Gifts sites to retired pages), so
  every list hides them by default and can show them.
- `GetBacklinkGap`: DataForSEO domain intersection for up to four domains against ours, the top
  1,000 referring domains, cached 30 days per set of domains.
- Not built: authority fed into keyword difficulty (2.1 uses DataForSEO's own difficulty).

**Screens.** SEO > Backlinks:

- Overview: rank, referring domains, backlinks and broken backlinks with the change since the
  previous week; new, lost and broken links of the last 30 days without our own websites; referring
  domains per week; the same figures for each competitor.
- Referring domains and Backlinks tabs: linking now, new, lost and (for links) broken, with or
  without our own websites.
- Backlink gap: pick competitors or type domains; referring domains that link to them and not to
  us, filtered to those linking to two or more by default.
- The Missing pages tab gets a Backlinks column: links pointing at each 404 path, sortable, so the
  redirects that win back the most links come first.
- A Fetch data button queues the fetch for the website and its competitors at once. A domain already
  fetched today is skipped, and the link lists of our own website are fetched only when due.

### 3.2 Competitor research

Built on 9 October 2026 on DataForSEO Labs, in the shop's own market (its country and language).

- `FetchDomainKeywords`: one Labs `ranked_keywords` request per domain (about $0.13) gives the
  keywords it ranks for in the top 100, up to 1,000 with the highest volume, and its organic summary.
  A domain fetched in the last four weeks is not fetched again, which is also the cache for domains
  typed into the tools.
  - `seo_domain_overviews`: domain, market, date, organic keywords, estimated traffic (DataForSEO's
    estimate from positions and volumes), keywords in the top 3 and top 10. Kept per date.
  - `seo_domain_keywords`: domain, market, keyword, position, URL, volume, estimated traffic,
    difficulty, intent. Replaced on every fetch.
- `FetchCompetitorResearch` (Mondays 02:00 UTC, `seo:fetch_domain_keywords {website?}`) keeps our
  websites and their shops' competitors at most four weeks old.
- `GetOrganicCompetitors`: Labs `competitors_domain` for our domain, cached 30 days, without our own
  websites and the competitors already set.
- Competitor domains are managed on SEO > Competitors (moved there from SEO > Keywords), and are
  used by Rankings, Backlinks and the tools below.

**Screens.** SEO > Competitors:

- Competitors: the shop's competitor domains, and the domains suggested by Google results (shared
  keywords, average position, organic keywords, estimated traffic) with an Add button.
- Domain comparison: our domain and up to four others, competitors by default, side by side: rank,
  referring domains and backlinks (3.1), organic keywords, estimated traffic, top 3 and top 10, and
  the share of keywords per intent, each group with its source. A typed-in domain without a backlink
  summary in the last five weeks gets one.
- Keyword gap: the same picker; Missing, Untapped, Weak, Strong, Shared and Unique with their
  counts (the groups overlap as in Semrush), filtered by intent and by the best position of any
  domain, with volume, difficulty, each domain's position and Track.

### 3.3 AI visibility

Built on 9 October 2026 on DataForSEO only (AI Optimization API): no prompts through the AI gateway
and no second model reading the answers. SEO > AI visibility, after Competitors.

**Sources, with the prices the first requests logged.**

- LLM Scraper (ChatGPT): what a ChatGPT user sees for a prompt in a chosen country (192 countries),
  with web search on: the answer in markdown, the sources it cites and the searches behind it.
  $0.004 per prompt live, answered in about 30 seconds. The standard queue costs $0.0012 but our
  first queued prompt was still not answered after 50 minutes, so on 9 October 2026 the team chose
  live.
- LLM Mentions: DataForSEO's database of AI answers to real searches, searched by domain: how often
  each domain is mentioned and the AI search volume behind those answers. $0.10 per request, and one
  request covers our domain and up to nine competitors. Its `chat_gpt` data is United States and
  English only; the `google` platform (AI Overviews) has 94 markets.
- AI Overviews of tracked keywords still come from the SERP checks of 2.2.

**What the answers taught us.** The scraper's `brand_entities` carry descriptions ("UK wholesale
gifts and incense supplier"), not names, and no URLs. So:

- An answer names a brand when it links to the brand's domain or writes one of its names, with or
  without spaces, hyphens and dots between the words. Image captions do not count. Our names are
  set per shop on the Prompts tab (default: the shop and website names); a competitor's are its
  domain and its name on the Competitors page.
- The place of a brand is its entry in the list the answer is built on: the heading level, numbered
  items, bold bullets or bold lines it uses most (at least twice). ChatGPT changes its answer
  between runs: asked twice the same day where to buy wholesale incense sticks in the UK, it put
  Ancient Wisdom second (citing thewholesaler.co.uk, not us) and then first (citing
  ancientwisdom.biz).

**Build.**

- `seo_ai_prompts`: shop_id, prompt (up to 500 characters), country, language, is_active,
  queued_at and last_run_at. Written by the team per shop; ten to twenty per brand is enough.
- `AskSeoAiPrompts` (Wednesdays 01:00 UTC) queues one `AskSeoAiPrompt` job for every active prompt
  not answered in the last 7 days, so the live calls run side by side on the workers; each job asks
  the scraper's live endpoint and stores the answer.
- `seo_ai_answers`: prompt_id, platform, date, model, answer (markdown), is_mentioned,
  brand_position, is_cited, brands (the answer's list, in order), competitors (json: named, cited
  and place per competitor), fan_out_queries.
- `seo_ai_citations`: answer_id, position, url, domain, title, competitor_id, and website_id and
  webpage_id when the URL is one of our live websites, matched by path like Search Console pages.
- `FetchSeoAiMentions` (1st of the month, 03:30 UTC): `google` in the shop's market when LLM Mentions
  has it, and `chat_gpt` (United States) for shops in English, stored per date in `seo_ai_mentions`.
  About $0.15 per shop a month.
- A Fetch data button asks the prompts that are due and fetches LLM Mentions of our domain and the
  competitors, once a day at most.

**Screens.** SEO > AI visibility:

- Share of voice: the share of answers of the last 28 days that name us and cite our website, next
  to each competitor, with the average place in the list; the weekly trend of the last 12 weeks;
  the LLM Mentions figures per platform with their change since the fetch before.
- Prompts: add, switch off and delete prompts; edit our brand names; per prompt the last answer
  (named, place, cited and with which pages, competitors named), the share of the last 28 days, and
  the full answer with its list and sources.
- Cited pages: our webpages by the number of prompts citing them, and the websites ChatGPT cites
  most, competitors and directories included: places worth being listed on.
- Top pages has an AI prompts column (3.6), the weekly SEO report an AI visibility section.

### 3.4 Content help

Built on 9 October 2026 on the AI gateway (OpenRouter, `gpt-5.6-luna`, under a tenth of a cent per
page). `WithPromptAI::promptSeo()` is still unused; the prompt lives in the new action.

- `GenerateSeoContentSuggestions` writes a page title and/or meta description for one webpage from
  what it shows (product name, code, description and family, or category, description and some of
  its products), the Google searches it appears for (Search Console, last 90 days) and the audit's
  length limits. The title limit is 60 characters minus the website's title prefix: Google cuts
  titles from the end, so a long suffix (AW UK's "- Ancient Wisdom - Giftware and Aromatherapy
  Supplier" is 54 characters on its own) can be cut but the page title should not be. It is told to
  state only facts from the page. Every call is logged in `seo_api_requests` with its cost and counts
  against the SEO API budget.
- `seo_content_suggestions`: webpage, field, the value when it was written, the suggestion, why
  (audit issue, low CTR, or asked for), state (pending, accepted, dismissed), who decided and when.
- `SuggestSeoContent` (Tuesdays 04:00 UTC, after the Sunday audits; `seo:suggest_content {website?}
  {--limit=}`), up to 30 pages per website: first the pages with at least 500 impressions in 28
  days, an average position of 10 or better and a CTR under 2%, then the title and description
  issues of the latest audit. "Title too long" only counts when the page title itself is too long,
  not when the website's own text makes it so. A page with a pending suggestion, or one decided in
  the last 90 days, is skipped for that field.
- Nothing is published by itself. `AcceptSeoContentSuggestion` writes the suggestion, possibly
  edited, through `UpdateWebpage`, so it shows in the webpage history; `DismissSeoContentSuggestion`
  keeps the page as it is.

**Screens.**

- Webpage, SEO and sharing panel: the pending suggestions, editable, with Use this and Dismiss, and
  a button that writes a new title and description on the spot.
- SEO > Site audit, Suggested fixes: every suggestion of the website, waiting, used or dismissed,
  with the same actions.

### 3.5 Competitor traffic and non-Google search demand

Decided on 9 October 2026: DataForSEO only, no Apify and no Similarweb.

**Competitor traffic.** DataForSEO Labs `bulk_traffic_estimation` returns the estimated monthly
traffic of up to 1,000 domains in one request, split into organic, paid, featured snippet and
local pack, and `historical_bulk_traffic_estimation` returns it per month back to October 2020, so
the trend of a competitor is there from the first fetch. It is estimated from rankings and
volumes, the same kind of figure as 3.2. Labs also offers clickstream-based traffic
(`clickstream_etv`) as a second estimate.

- What it cannot give is traffic from other channels (direct, referral, social, email). Only
  Similarweb has that: its API is licensed and priced through sales, far above the SEO budget, and
  the Apify actors that read it break Similarweb's terms. Not built unless the team uses Semrush
  Traffic Analytics today and needs it.
- Built on 9 October 2026 with `historical_bulk_traffic_estimation`: the first request (two UK
  domains) cost $0.1224, the same for the whole history since October 2020 as for the last 12
  months, so every fetch reads it all. `bulk_traffic_estimation` is not needed: the history ends
  with the latest month.
- `FetchDomainTraffic` stores per domain, market and month the organic, paid, featured snippet and
  local pack visits and the organic and paid keyword counts in `seo_domain_traffic`, from the first
  month the domain is seen. A domain fetched in the last 25 days is not fetched again; one with no
  traffic at all gets an empty row so it is not asked for again until then.
- `FetchCompetitorTraffic` (2nd of the month, 02:30 UTC): one request per market (country and
  language) for the live websites in it and all their competitors.
- Domain comparison: a Search traffic section (organic visits of the latest month, change against
  the same month a year before, paid visits, when fetched) and a chart of organic visits per month
  over the last three years. Compare fetches a typed-in domain's history with the rest; a failed
  traffic fetch leaves the rest of the comparison.
- Competitors tab: the latest organic visits of our domain and each competitor.
- In the first fetch, ancientwisdom.biz had about 2,600 organic visits in September 2026 against
  8,200 a year before, and somethingdifferentwholesale.com about 5,700 against 31.

**Search demand outside Google.** Only when the team sells or advertises on another platform:

- Bing: the Bing Webmaster Tools API (`GetKeywordStats`, `GetRelatedKeywords`) is free and needs
  each website verified in Bing Webmaster Tools; DataForSEO also has Bing keyword data.
- Amazon: DataForSEO Labs has Amazon keyword data.
- Platforms that publish no search volume (TikTok, Pinterest, Etsy): not covered.

### 3.6 Top pages

Built on 9 October 2026. The Webpages tab of the SEO dashboard is now Top pages
(`IndexWebpagesPerformance`).

- For the dashboard interval and the period before it (same number of days, ending the day before
  the interval starts): visitors and page views from `webpage_time_series_records`; Search Console
  clicks, impressions and position from `search_console_page_days`, whose periods end on the last
  day Search Console has data for, not today. With the interval set to All there is nothing to
  compare with, and the table says so.
- Each figure shows its change under it: a percentage, or the plain difference when both periods
  are under 20 (`MIN_FOR_PERCENT`), with the previous value and the difference on hover. Position
  shows the places gained or lost.
- Filters Growing, Dropping, New (no visitors before) and Lost (no visitors now), on visitors.
  Growing and Dropping sort by the change, so the biggest drops come first. Lost pages are listed
  even with no traffic in the interval.
- New columns: Queries (different Google searches the page appeared for) and Referring domains
  (from `seo_backlinks` of 3.1, our own websites left out; that list holds one link per linking
  domain plus the new, lost and broken ones, so it counts domains better than links).
- AI prompts: the prompts whose ChatGPT answer cited the page in the last 28 days, whatever the
  interval (3.3).

### Phase 3 is done when

- Backlink summaries and gaps update weekly for our domains and the competitors in `seo_competitors`,
  with new, lost and broken backlinks for our own domains.
- Domain comparison, keyword gap and AI visibility are on the dashboard.
- Top pages shows the change against the previous period, referring domains and the AI prompts that
  cite each page.
- Competitor search traffic, with its history, is fetched monthly and shown in the domain comparison.

## API usage

Built on 9 October 2026, once the providers were settled (DataForSEO and the AI gateway). Website >
API usage at group level (`ShowSeoApiUsage`, `GetSeoApiUsage`), next to the SEO portfolio, and
linked from every SEO dashboard: the figures and the budget are the group's.

- **Budget setting.** The monthly cap for all SEO APIs together, default 250 USD, set on the page by
  someone with `group-webmaster.edit` or `sysadmin.edit` (`UpdateSeoApiBudget`) and kept in the
  group settings (`seo.api_monthly_budget`). `SEO_API_MONTHLY_BUDGET` is only the default.
- **This month.** Spend against the budget with a bar, what is left, the month end at the current
  daily pace, requests and failed requests. A warning at 80% or when the pace would pass the budget,
  and a notice once it is spent and paid calls stop.
- **Breakdown.** Spend, requests and failures per feature and provider, the feature read from the
  endpoint logged in `seo_api_requests` (Labs `ranked_keywords` counts as competitor research even
  when keyword research asked for a URL); spend per day; the previous six months, each opening its
  own month.
- **Errors.** The latest twenty failed requests with their message, website and endpoint, so an
  expired key, an empty balance or a paused account shows up here first.
- Feature screens show no spend of their own.

## Not planned yet

Found on 9 October 2026 by comparing this plan with the marketing team's research ("AIKU SEO
Strategy (Research)", 8 October 2026). None is built; each needs the team's go-ahead.

- **Position history and alerts.** Built on 9 October 2026. In the Rankings tab a keyword opens its
  position chart (`GetSeoKeywordHistory`): every check, the competitors from the same checks and the
  Search Console average of the same query per day, position 1 at the top and a gap where the
  website was not in the results read. Anyone can watch a keyword (the bell, `seo_keyword_watchers`).
  After each check `StoreSerpResult` writes a `seo_ranking_alerts` row when the keyword left the top
  10, dropped out of the results read, or fell 5 places or more (`SeoRankingAlert::DROP_PLACES`);
  the row shows as a tag in the table, and once the checks are collected `NotifySeoRankingAlerts`
  sends each watcher one notification per shop, in the bell and by email. No API cost.
- **Tracking depth per keyword.** Not built: on 12 October 2026 the marketing team said the top 30
  is enough and that checks are mostly wanted on request, weekly or monthly, which 2.2 now does. The
  research had recommended daily top 100, about $486 a month for 2,700 keywords.
- **Export and scheduled reports.** Built on 9 October 2026.
  - An Excel button on every SEO table (Rankings, Tracked keywords, Top pages, Missing pages,
    Referring domains, Backlinks, Keyword gap, SEO portfolio, and since HELP-3885 Search queries, Search
    opportunities, the pages of each Site audit issue and Suggested fixes) downloads it with the filters and sort
    it has on screen, up to 20,000 rows (`ExportSeoTable`, `ExportSeoPortfolio`, `SeoTableExport`).
    The same index actions are read in pages of 2,000 for the request.
  - Weekly report by email: anyone can turn it on per shop (SEO dashboard) or for every website (SEO
    portfolio), kept in `seo_report_subscriptions`. `SendSeoWeeklyReports` (Mondays 07:00 UTC, after
    the weekly checks) sends each subscriber the shop's week against the week before: visitors,
    Google clicks, impressions and position, tracked keywords in the top 10 with the five biggest
    winners and losers, site health, referring domains with new and lost, new 404 paths and the
    suggested fixes waiting; the portfolio report lists every website.
- **Portfolio view.** Built on 9 October 2026: Website (group menu) > SEO portfolio (`ShowSeoPortfolio`, `GetSeoPortfolio`), linked from every SEO dashboard,
  one row per live website: site health of the latest audit and its change, visitors and Google
  clicks of the last 28 days against the 28 before (Search Console ending on its own last day),
  average position, tracked keywords in the top 10, referring domains with the week's new and lost,
  and DataForSEO rank, and the answers of the last 28 days that name the shop in ChatGPT (3.3);
  totals above, each website opening its shop's SEO dashboard. From stored data only.
- **Content briefs.** Built on 12 October 2026, after the marketing team agreed to it. SEO >
  Keywords, tab Content briefs: a keyword, country and language give a writer's brief in about half
  a minute, for about half a cent (one live SERP check, $0.002, and one AI gateway call, $0.0026 in
  the first briefs).
  - `GenerateSeoContentBrief` (a job) reads Google's top 10 for the keyword with the questions and
    related searches it shows, fetches those pages side by side and reads their headings, length
    and opening text (`ReadPage`, menus and footers left out), and finds our own page for the
    search: from Search Console, else in the results, else from the rank tracking.
  - The AI gateway writes the brief from that: search intent, kind of page, whether to improve our
    page or write a new one, title, meta description, H1, an outline with notes, questions to
    answer, terms to use and where to do better than the ranking pages. Guidance is in English, the
    copy to use in the keyword's language. Lengths and counts are measured, not asked from the
    model, and numbers or claims about our range may only come from our own page.
  - Pages that block robots (Cloudflare, marketplaces) are marked as not read and only their Google
    title and snippet are used; in the first two briefs 8 of 10 and 6 of 9 pages were read. Only
    public http and https addresses are fetched.
  - The list shows each brief with its suggested title and the typical length; a brief opens in
    full, copies as text for the writer, can be written again (Google read afresh) or deleted. A
    brief still pending after 10 minutes counts as failed and can be asked for again.
- **Competitor page changes.** The research proposes Apify to watch competitor pages. Our crawler
  can fetch a short list of competitor URLs weekly and report title, content and price changes,
  without Apify. Optional.

## Cancelling Semrush

Run as the pilot the marketing research proposes, with its owners:

1. **Billing and usage audit** (account owner): the Semrush invoice broken down into plan, seats and
   add-ons, and the tracked keywords with their devices and locations.
2. **Pilot scope** (Aldo and Gita): two or three websites, their tracked keywords and competitors
   set in Aiku, and which Semrush features the team uses each week.
3. **Pilot on those websites** (Aldo and the SEO team), for at least one full Semrush billing cycle,
   both tools side by side. Compare audit issue counts, positions for a sample of tracked keywords,
   referring domain counts and keyword gap results; write down where they differ and why. Record the
   actual API spend from the API usage page.
4. **Build-or-buy decision** (Tomas): keep, reduce or cancel the paid Semrush features, against the
   pilot criteria of the research: useful data coverage, reliable runs, team adoption, total monthly
   cost and payback period.
5. Before cancelling, export from Semrush whatever history the team wants to keep (position history,
   backlink history), because Aiku's own history only starts on the day each fetch was switched on.

## Decisions needed

| Decision | Needed before |
| --- | --- |
| Who adds the service account to the properties that are still missing | Phase 1 |
| SERP provider and monthly budget (proposal in [budget.md](budget.md)) | Phase 2 |
| Tracked keyword list, locations and devices per shop, and check frequency (set in SEO > Keywords) | Phase 2 |
| Competitor domains per shop (set in SEO > Competitors) | Phase 2 (positions) and Phase 3 (backlinks) |
| Prompts for AI visibility and our brand names, per shop (set in SEO > AI visibility > Prompts) | Phase 3 |
| Which non-Google platforms to collect search signals from, if any | Phase 3 |
| Which two or three websites to pilot on, and how long to run side by side before cancelling | Pilot |
| Whether to build the parts in [Not planned yet](#not-planned-yet) | Pilot |
