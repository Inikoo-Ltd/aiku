# Status

Where each part of the [implementation plan](implementation-plan.md) stands, as of 8 October 2026.
Nothing here has been deployed yet.

## Done

| Part | What it does | Where in Aiku |
| --- | --- | --- |
| SEO dashboard | Website and webpage performance from Aiku's own tracking, filtered by the dashboard interval | SEO |
| Visitors, Page views | The last 30 days of visits and page views, per webpage and per visitor; each visitor carries its source and can be filtered by channel and by bounced or engaged | SEO > Visitors, SEO > Page views |
| Webpage traffic sources | Where the arrivals on one webpage came from, last 90 days | Webpage > Traffic sources tab |
| Phase 0: data fixes | Smartphones and phablets count as mobile; session duration caps idle time at 30 minutes; `pagespeed` props renamed to `real_user_speed` | SEO dashboard figures |
| 1.1 Search Console history | 16 months of clicks, impressions, CTR and position, fetched daily, per website, page and query | SEO dashboard: Google Search card, Search queries and Low CTR queries tabs, search columns on Webpages; Webpage > Performance tab |
| 1.2 Site Audit | Weekly crawl of every live website, 28 issue types (hreflang included), health score and trend, pages per issue | SEO > Site audit |
| 1.3 404 log | Paths that returned 404, by hits, with a Create redirect action | SEO > Missing pages |
| 1.4 Page speed | Core Web Vitals (LCP, INP, CLS) from real visits, per website and per page, following HELP-3303: Google's Chrome UX Report and visitors' browsers | SEO dashboard: Page speed card and Page speed tab |
| 2.1 Keyword research | Volume, 12 month trend, difficulty, intent and CPC for up to 5 seed keywords and the keywords that contain them, or the keywords a URL ranks for, through DataForSEO Labs; Search Console queries with the same words | SEO > Keywords: Research tab |
| Tracked keywords and competitors | The keyword list and competitor domains per shop, set by the team | SEO > Keywords: Tracked keywords and Competitors tabs |
| 2.2 Rank tracking | Google position of every tracked keyword, weekly (top 30) or daily (top 20), with SERP features, AI Overview citations and competitor positions; volumes refreshed monthly | SEO > Keywords: Rankings tab |
| 3.1 Backlinks | Weekly rank, referring domains and backlinks for our websites and competitors; our links one by one every four weeks with new, lost and broken; backlink gap against up to four domains; backlinks per 404 path | SEO > Backlinks; SEO > Missing pages |
| Weekly external link check | Rechecks the status of every external link | Scheduled, Sunday 02:00 UTC |

## Skipped

| Part | Why |
| --- | --- |
| 1.4 lab scores (PageSpeed Insights) | HELP-3303 (commit `92f37c381f`) replaced the Lighthouse lab test with field data, because the lab score sent staff after numbers customers never saw. 1.4 uses that field data instead. The old `pagespeed_*` columns on `webpage_time_series_records` keep their history until the owner of HELP-3303 decides to drop them. |

## Not started

### Phase 2: keywords

| Part | What has to happen first |
| --- | --- |
| Switching rank tracking on | Approve the monthly budget ([budget.md](budget.md)) and fill the tracked keyword list and competitors; nothing is checked while the list is empty |

### Phase 3: the outside world

| Part | What has to happen first |
| --- | --- |
| 3.2 Domain comparison and keyword gap | Nothing: DataForSEO Labs is already the provider; uses the competitors set in SEO > Keywords |
| 3.3 AI visibility | Choose the models (with web search on) and write the prompts per shop; check the SEO provider's AI mention data first |
| 3.4 Content help | Nothing; can start any time. The first Site Audit found many product pages with an empty meta description, which is where this would start. |
| 3.5 Competitor traffic | Apify actor (cheap, against Similarweb's terms) or the Similarweb API (licensed, priced through sales) |
| 3.5 Non-Google demand | Choose the platforms; Bing Webmaster Tools keyword data is free |
| 3.6 Top pages | Nothing for the traffic and Search Console change against the previous period; can start any time. Referring domains and AI citations per page wait for 3.1 and 3.3. |

### Last: API usage

| Part | What has to happen first |
| --- | --- |
| API usage page: spend of all SEO APIs against one monthly budget (default 250 USD, set on the page), per provider and feature, with errors | Built after the paid providers of Phase 3 are in place |

### Still open in Phase 1

- Compare Site Audit results with a Semrush Site Audit of the same website before the team relies on
  them.

## After deploying

1. `php artisan migrate`
2. Once, to correct the last 30 days of the dashboard figures:
   `php artisan maintenance:recalculate_website_visitor_durations`, then
   `php artisan websites:redo_time_series --from=<30 days ago> --to=<today>`.
3. Once, to give the visitors already recorded a source:
   `php artisan maintenance:classify_website_visitor_traffic_sources`.
4. Once, to load 16 months of Search Console history: `php artisan search_console:fetch --async`.
5. Add the service account named on the Google Search card as a user on every Search Console
   property that is still missing.

Scheduled from then on: Search Console fetch daily at 02:30 UTC, site audits Sunday 03:00 UTC,
external link check Sunday 02:00 UTC, 404 path pruning daily at 03:50 UTC, tracked keyword volumes
daily at 00:15 UTC, Google checks queued daily at 00:30 UTC and collected every 15 minutes, backlinks Mondays at
01:00 UTC.

## Configuration

| Variable | Used for | Needed |
| --- | --- | --- |
| `GOOGLE_OAUTH_CLIENT_SECRET` (or group setting `gcp.oauthClientSecret`) | Search Console | Yes |
| `GOOGLE_CRUX_API_KEY` | Chrome UX Report: Real user speed and the Google side of 1.4. The key needs the Chrome UX Report API enabled in its Google Cloud project | Yes |
| `DATAFORSEO_LOGIN`, `DATAFORSEO_PASSWORD` | Keyword research (2.1), and rank tracking, backlinks and competitor data later | Yes, for SEO > Keywords |
| `SEO_API_MONTHLY_BUDGET` | One monthly budget in USD for all paid SEO APIs together; no billable call is made once the month's spend reaches it. Becomes a setting on the API usage page | No, defaults to 250 |
| `GOOGLE_ADS_DEVELOPER_TOKEN` | Nothing in SEO; keyword data comes from DataForSEO | No |
| `GOOGLE_PAGESPEED_API_KEY` | Nothing; 1.4 uses field data, not PageSpeed Insights | No |
| `OPENROUTER_API_KEY` | AI visibility and content help (Phase 3) | Not yet |
