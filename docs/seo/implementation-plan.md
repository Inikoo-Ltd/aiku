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
- **Provider calls go through one client per provider**, with the API key in `config/services.php`,
  a per-run budget cap, and a log row per request in `seo_api_requests` (provider, endpoint, rows,
  duration, error, cost if the provider reports it). Phase 2 and 3 costs are only visible this way.
  `App\Services\SearchConsole\SearchConsoleClient` is the first one.
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

**Today.** `app/Actions/Web/Crawl/CrawlWebsite.php` is a cache warmer. It requests the most visited
canonical URLs until 90% of the last 30 days of traffic is covered and throws the responses away.
The `crawls` table records runs, not pages. Total concurrency is capped at 8 and it only runs in
production.

**Build.**

- Add a crawl type `audit` to the existing `crawls` table and keep the cache warmer as it is.
- `AuditWebsite` starts from the sitemap index and follows internal links. It respects
  `robots.txt`, identifies itself with its own user agent, and shares the existing concurrency cap.
- `crawl_pages`: crawl_id, url, status_code, redirect_to, redirect_hops, response_ms, bytes, depth,
  title, meta_description, canonical, robots meta, h1_count, is_in_sitemap, is_indexable,
  webpage_id.
- `crawl_issues`: crawl_id, crawl_page_id, type, severity, details (json). Issue types to start with:
  - 4xx and 5xx responses, and internal links pointing at them
  - redirect chains and redirect loops
  - missing, duplicate, too long or too short title and meta description
  - missing or conflicting canonical, canonical pointing at a redirect or a 404
  - noindex pages listed in the sitemap, indexable pages missing from it
  - images without alt text, pages with more than one h1, slow responses
- A site health score per crawl: share of crawled pages with no error-level issue. Store it on the
  crawl row so the trend is one query.
- Weekly per website, plus a button on the dashboard to run one now. Keep the last 10 audits per
  website.
- Schedule `CheckExternalLinkStatus` weekly for `external_links`. Today it only runs during the
  Aurora import, so the stored status goes stale.

**Screens.** A Site Audit tab: health score and its trend, issues grouped by type with counts, and a
page list per issue that links to the webpage in Aiku.

### 1.3 404 log

**Today.** `ShowIrisWebpage` calls `abort(404)` and nothing is recorded.

**Build.**

- Before the abort, dispatch `RecordWebsiteNotFoundHit` (queue `analytics`) with the website, path
  and referrer. Skip bots with the existing `IsBot`.
- `website_not_found_paths`: website_id, path, first_seen_at, last_seen_at, hits, last_referrer,
  is_resolved. Upsert one row per path, so the table holds paths, not hits.
- On the dashboard, list paths by hits with a "Create redirect" action that opens the existing
  redirect form (`app/Actions/Web/Redirect/`) prefilled with the path. Creating the redirect marks
  the row resolved.

### 1.4 PageSpeed scores

**Today.** Migration `2026_09_11_072327_add_pagespeed_to_webpage_time_series_records_table.php`
added `pagespeed_{desktop,mobile}_{performance,accessibility,best_practices,seo}`. Nothing writes
them and nothing calls the PageSpeed Insights API. `ProcessWebpageTimeSeriesRecords` uses
`upsertTimeSeriesRecords()`, which only updates the columns it writes, so values stored here
survive the nightly rebuild.

**Build.**

- `FetchPageSpeedScores` weekly per website: one run per page type for a sample of pages, plus the
  most viewed pages of the last 28 days. Both strategies, mobile and desktop.
- Because of the ground rule above, store the scores in `pagespeed_runs` (webpage_id, strategy,
  fetched_at, the four category scores, LCP, CLS, TBT from the lab run) instead of the time series
  columns. Drop the unused columns once nothing reads them.
- Show lab scores next to the CrUX field data the pages already show. Label them as lab and field;
  they disagree often and both are right.

### Phase 1 is done when

- Search Console history is stored for every website with a property, and the webpage tab reads it. (Done)
- Every website has had at least one audit, and the issue list matches a Semrush Site Audit run on
  the same site closely enough that the team trusts it.
- 404 paths are visible and redirects can be created from them.
- PageSpeed scores are fetched weekly.

## Phase 2: keywords

### 2.1 Keyword research

**Today.** Shops connect a Google Ads account through OAuth (`app/Actions/CRM/Customer/GoogleAds/`).
Keyword code exists only for paid search: `app/Actions/CRM/TrafficSourceCampaign/GoogleAds/`.

**Build.**

- `GetKeywordIdeas` calls `KeywordPlanIdeaService.GenerateKeywordIdeas` with the shop's existing
  Google Ads connection, for a seed keyword or a URL, a location and a language.
- `seo_keywords`: shop_id, keyword, location_id, language, avg_monthly_searches, monthly_searches
  (json, 12 months), competition, low and high top-of-page bid, fetched_at. Refresh monthly for
  saved keywords.
- Google can return less precise volumes for accounts with little ad spend. Check what our accounts
  receive before relying on the numbers, and show the source next to every volume.
- Keyword difficulty: compute our own from the top 10 results of the keyword (their referring
  domains once Phase 3 lands, SERP features, and whether our page is already in Search Console for
  it). Until Phase 3, show it without the backlink part and label it as partial.

**Screens.** A Keywords tab: search by seed or URL, results with volume, trend and difficulty, and a
"Track" action that adds the keyword to rank tracking.

### 2.2 Rank tracking

**Build.**

- Pick one SERP provider (DataForSEO, SerpApi, or similar) and one client class for it. Do not
  scrape Google from our servers: it breaks Google's terms and needs a proxy pool.
- `seo_tracked_keywords`: shop_id, keyword_id, location_code, language, device, target_webpage_id
  (nullable), frequency (daily or weekly), is_active.
- `seo_keyword_rankings`: tracked_keyword_id, date, position (null when not in the top 100),
  ranking_url, webpage_id, serp_features (json: featured snippet, AI Overview, local pack, ads,
  shopping), and whether our page is in the AI Overview.
- `seo_competitors`: shop_id, domain, label. Competitor positions come from the same SERP response,
  so they cost nothing extra: `seo_competitor_rankings` (tracked_keyword_id, competitor_id, date,
  position, url).
- Use the provider's queued mode where it is cheaper than live results; rankings are not needed in
  real time.
- Show the Search Console average position beside the tracked position. They measure different
  things (an average over all impressions against one check from one location) and the team should
  see both.

**Cost control.** Cost grows with keywords × locations × devices × checks per month. Agree the list
per shop and the frequency before switching it on, set the per-run budget cap, and show the month's
spend on the dashboard.

**Screens.** A Rankings tab: visibility (share of tracked keywords in the top 3, top 10, top 100),
position changes since the previous check, winners and losers, competitors on the same keywords.

### Phase 2 is done when

- Every shop has its tracked keyword list, checked on schedule within budget.
- For a sample of keywords, positions match Semrush Position Tracking for the same location and
  device, give or take the day-to-day movement both tools show.

## Phase 3: the outside world

### 3.1 Backlinks

**Build.**

- One backlink provider (DataForSEO Backlinks, Ahrefs API, or similar). The Search Console API has
  no links endpoint, so there is no free source.
- Weekly per website and per competitor domain:
  - `seo_backlink_summaries`: domain, date, backlinks, referring_domains, new and lost since the last
    run, the provider's authority score.
  - `seo_referring_domains`: domain, referring_domain, first_seen, last_seen, backlinks, authority,
    is_lost.
- Backlink gap: referring domains that link to two or more competitors and not to us.
- Feed referring domains into the keyword difficulty from 2.1.

### 3.2 Competitor research

**Build.**

- From the same provider family: ranked keywords per competitor domain and organic competitors per
  shop domain, monthly.
- `seo_domain_keywords`: domain, keyword, location, position, url, estimated_traffic, fetched_at.
- Keyword gap: keywords where a competitor ranks in the top 20 and we do not rank, sorted by volume.
- Label every traffic figure for a domain we do not own as the provider's estimate.

### 3.3 AI visibility

**Today.** `app/Actions/Helpers/AI/` already sends prompts through an AI gateway (OpenRouter) for
product titles and descriptions.

**Build.**

- `seo_ai_prompts`: shop_id, prompt, language, is_active. Prompts are the questions a customer would
  ask, written by the team per shop.
- Weekly, send each prompt to the models the team cares about through the gateway.
  `seo_ai_answers`: prompt_id, model, run_at, answer, brand_mentioned, competitor_mentions (json),
  cited_urls (json).
- AI Overviews come from the SERP results already fetched in 2.2, not from the gateway.
- Models change answers between runs. Show mention rate over several runs, not one answer.

### 3.4 Content help

- `WithPromptAI::promptSeo()` exists and nothing calls it. Use the gateway to suggest meta titles
  and descriptions for pages that the audit flags, and for pages with high impressions and low CTR.
- Suggestions are never published automatically. Someone accepts them on the webpage SEO panel.

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
- Apify charges per run or per result, depending on the actor. Track it with the same per-request
  log and budget cap as the other providers.

**Decision.** Choose between an Apify actor (cheap, against Similarweb's terms, may break) and the
Similarweb API (licensed, priced) for competitor traffic before building it.

### Phase 3 is done when

- Backlink summaries and gaps update weekly for our domains and the competitors in `seo_competitors`.
- Keyword gaps and AI visibility are on the dashboard.
- Competitor domain traffic is fetched monthly, and Bing and non-Google search signals are shown
  beside Google volumes.

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
| SERP provider and monthly budget | Phase 2 |
| Tracked keyword list, locations and devices per shop, and check frequency | Phase 2 |
| Backlink and competitor data provider (ideally the same as the SERP one) | Phase 3 |
| Competitor domains per shop | Phase 2 (positions) and Phase 3 (backlinks) |
| Models and prompts for AI visibility | Phase 3 |
| Competitor domain traffic: Apify actor or the Similarweb API | Phase 3 |
| Which non-Google platforms to collect search signals from | Phase 3 |
| How long to run side by side before cancelling | After Phase 3 |
