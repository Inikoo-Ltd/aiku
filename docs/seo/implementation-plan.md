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
- **One monthly budget for all paid SEO APIs**, not one per provider or feature: DataForSEO, Apify
  and the AI gateway calls for AI visibility count against the same cap (default 250 USD,
  `SEO_API_MONTHLY_BUDGET` until the [API usage page](#api-usage) makes it a setting).
  `App\Services\SeoApi\SeoApiBudget` adds up the month's cost in `seo_api_requests`; every client
  checks it before a billable call. Reading results already paid for is never blocked. Feature
  screens do not show the spend; the API usage page does.
- **The SEO dashboard stays the entry point.** Each phase adds sections or tabs to
  `ShowSeoDashboard` (`app/Actions/Web/Website/UI/ShowSeoDashboard.php`), filtered by the dashboard
  interval like the existing performance card.

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
  - Search queries: by query, with clicks, impressions, CTR, position and the number of pages shown.
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
- User agent `Mozilla/5.0 (compatible; AikuSiteAuditBot/1.0)`. It is detected as a bot, so audits do
  not appear as visitors.
- Redirects are not followed by the HTTP client. Each hop is its own row, so chains and loops are
  visible.
- HTML is parsed with PHP 8.4 `Dom\HTMLDocument`. No package was added.
- Default limit 10,000 pages per audit. At about 1.6 s per page on the storefronts this is roughly
  2.5 hours at concurrency 2, inside the 3 hour job timeout of `long-low-priority`.
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
every 5 seconds.

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

**Screen.** SEO > Missing pages lists paths by hits with last seen, first seen and where the last
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
shop's), results with intent, volume, a 12 month trend, difficulty, CPC and ad competition, a filter
per intent. Below them, the Search Console queries of the website
that contain the same words (last 90 days). Every result has a Track action.

### Tracked keywords and competitors (set from the UI)

- `seo_tracked_keywords`: shop, keyword (lower case), country, language, device (mobile, desktop),
  check frequency (weekly, daily), optional target webpage, active. Unique per shop, keyword,
  country, language and device.
- `seo_competitors`: shop, domain (stored without scheme, `www.` and path), optional name.
- SEO > Keywords, tab Tracked keywords (competitors: SEO > Competitors): add, change device and frequency inline,
  pause, remove. Editing needs the same web edit permission as the other SEO actions
  (`WithSeoEditAuthorisation`).
- Nothing is fetched for them yet; that is 2.2.

### 2.2 Rank tracking

Built on 9 October 2026 on DataForSEO's SERP API.

- `PostSerpTasks` (daily 00:30 UTC) queues a Google check for every active tracked keyword that is
  due: daily ones every day, weekly ones once seven days have passed. It uses the standard queue
  ($0.0006 per page of 10 results), reading weekly keywords to the top 30 and daily ones to the top
  20, as in [budget.md](budget.md). Tasks carry a tag with a hash of `APP_URL`, so environments
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

**Screen.** SEO > Keywords, tab Rankings:

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
- SEO > Missing pages gets a Backlinks column: links pointing at each 404 path, sortable, so the
  redirects that win back the most links come first.
- Locally only, a Fetch now button runs the fetch for the shop at once.

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

**Today.** `app/Actions/Helpers/AI/` already sends prompts through an AI gateway (OpenRouter) for
product titles and descriptions.

**Build.**

- `seo_ai_prompts`: shop_id, prompt, language, is_active. Prompts are the questions a customer would
  ask, written by the team per shop.
- Weekly, send each prompt to the models the team cares about through the gateway: at least
  ChatGPT (OpenAI), Gemini, Claude and Perplexity.
- Use each model with web search on (OpenRouter's `:online` variants, Perplexity Sonar). Without it
  the model answers from training data, cites nothing, and does not behave like the ChatGPT or
  Gemini apps customers use. Even with search on, API answers differ from the apps, so treat the
  result as a sample, not what every customer sees.
- `seo_ai_answers`: prompt_id, model, run_at, answer, brand_mentioned, brand_position (the order in
  which our brand appears among the brands named), competitor_mentions (json).
- `seo_ai_citations`: answer_id, url, domain, position, competitor_id (nullable), website_id and
  webpage_id when the URL is ours, matched like Search Console pages.
- AI Overviews come from the SERP results already fetched in 2.2, not from the gateway.
- Check whether the SEO provider offers AI mention data (DataForSEO has an AI optimisation API)
  before building the sending side ourselves, and compare its price with the gateway's.
- Models change answers between runs. Show mention rate over several runs, not one answer.

**Screens.** An AI visibility tab:

- Mention rate and citation rate per model, ours next to each competitor (share of voice), with the
  trend over the weekly runs.
- Prompts: per prompt, which models mention us, which cite us and with which page, and which
  competitors they name instead.
- Cited pages: our webpages by the number of prompts and models that cite them.

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

### 3.5 Apify for competitor traffic and non-Google search demand

Neither gap can be measured by us, and the SEO providers in 3.1 and 3.2 only estimate organic
search traffic. [Apify](https://apify.com) runs third-party scrapers ("actors") on demand and
returns JSON, which can cover both, with the limits below.

**Total traffic of a competitor's domain.** Several actors read Similarweb's public data for a
domain, for example [fetch_cat/similarweb-traffic-scraper](https://apify.com/fetch_cat/similarweb-traffic-scraper)
and [bovi/similarweb-scraper](https://apify.com/bovi/similarweb-scraper). They return monthly
visits, global and category rank, bounce rate, pages per visit, traffic channel mix and top
countries.

- What we get is Similarweb's model, not a measurement. It is the same kind of figure Semrush Traffic
  Analytics shows, from a different panel, so the two will not match.
- Small domains often have no figure at all. Expect gaps for niche competitors.
- These actors read Similarweb pages or an undocumented endpoint without a Similarweb account, which
  is against Similarweb's terms and breaks whenever Similarweb changes its site. The Similarweb API
  is the licensed way to get the same data, at a subscription price.
- Monthly granularity is enough. Run once a month per competitor domain.

**Search demand outside Google.**

- Bing has an official source and it is free: the Bing Webmaster Tools API (`GetKeywordStats`,
  `GetRelatedKeywords`) returns Bing impressions per keyword, country and language. Use it before
  Apify for Bing.
- For Amazon, YouTube, TikTok and similar platforms, no platform publishes search volume. Actors
  such as [Answer The Public](https://apify.com/deadlyaccurate/answer-the-public) collect what those
  platforms suggest as people type. That tells us which searches exist and which are popular enough
  to be suggested, not how many searches each one gets.
- Show these keywords as demand signals (suggested, rising, related) next to Google volumes, never in
  the same volume column.

**Build.**

- One `ApifyClient` that starts an actor run, waits for it or receives the webhook, and reads the
  dataset. API token in `config/services.php`.
- Pin each actor to a version and check its output shape before storing, so a changed actor fails
  loudly instead of writing empty rows.
- `seo_domain_traffic_estimates`: domain, month, source (`similarweb_via_apify` or
  `similarweb_api`), visits, channel_mix (json), top_countries (json), rank, fetched_at.
- `seo_platform_keyword_signals`: shop_id, platform, keyword, signal (suggested, related, rising),
  country, language, fetched_at.
- Apify charges per run or per result, depending on the actor. Log it in `seo_api_requests` with
  its cost, so it counts against the shared SEO API budget.

**Decision.** Choose between an Apify actor (cheap, against Similarweb's terms, may break) and the
Similarweb API (licensed, priced) for competitor traffic before building it.

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
- Waits for 3.3: the AI prompts that cite each page.

### Phase 3 is done when

- Backlink summaries and gaps update weekly for our domains and the competitors in `seo_competitors`,
  with new, lost and broken backlinks for our own domains.
- Domain comparison, keyword gap and AI visibility are on the dashboard.
- Top pages shows the change against the previous period, referring domains and the AI prompts that
  cite each page.
- Competitor domain traffic is fetched monthly, and Bing and non-Google search signals are shown
  beside Google volumes.

## API usage

Built last, once every paid provider of Phase 2 and 3 is in place. One page for the cost of all of
them, so the monthly budget is watched and changed in Aiku instead of in each provider's dashboard.

- **Budget setting.** The monthly cap for all SEO APIs together, default 250 USD, set on the page by
  someone with web edit permission and stored as a group setting. It replaces
  `SEO_API_MONTHLY_BUDGET`, which stays only as the default.
- **This month.** Spend against the budget, what is left, and the projected month end at the
  current daily rate. A warning at 80%, and a clear notice when the cap is reached and calls stop.
- **Breakdown.** Spend, requests and errors per provider and per feature (keyword research, rank
  tracking, volume refresh, backlinks, competitor research, AI visibility, Apify), from the
  `provider` and `endpoint` of `seo_api_requests`; daily spend over the month; the previous months.
- **Errors.** The latest failed requests with their message, so an expired key or an empty balance
  shows up here before anyone notices missing data.
- Feature screens show no spend of their own.

## Cancelling Semrush

1. Run Aiku and Semrush side by side for at least one full Semrush billing cycle after Phase 3.
2. Compare, for the same sites: audit issue counts, positions for a sample of tracked keywords,
   referring domain counts. Write down where they differ and why.
3. Export from Semrush whatever history the team wants to keep (position history, backlink history),
   because Aiku's own history only starts on the day each fetch was switched on.
4. Cancel when the team agrees the Aiku figures answer the same questions.

## Decisions needed

| Decision | Needed before |
| --- | --- |
| Who adds the service account to the properties that are still missing | Phase 1 |
| SERP provider and monthly budget (proposal in [budget.md](budget.md)) | Phase 2 |
| Tracked keyword list, locations and devices per shop, and check frequency (set in SEO > Keywords) | Phase 2 |
| Competitor domains per shop (set in SEO > Competitors) | Phase 2 (positions) and Phase 3 (backlinks) |
| Models and prompts for AI visibility, and whether to buy AI mention data from the SEO provider | Phase 3 |
| Competitor domain traffic: Apify actor or the Similarweb API | Phase 3 |
| Which non-Google platforms to collect search signals from | Phase 3 |
| How long to run side by side before cancelling | After Phase 3 |
