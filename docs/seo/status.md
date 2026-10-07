# Status

Where each part of the [implementation plan](implementation-plan.md) stands, as of 7 October 2026.
All work is on the `seo` branch and has not been deployed yet.

## Done

| Part | What it does | Where in Aiku |
| --- | --- | --- |
| SEO dashboard | Website and webpage performance from Aiku's own tracking, filtered by the dashboard interval | SEO |
| Visitors, Page views | The last 30 days of visits and page views, per webpage and per visitor | SEO > Visitors, SEO > Page views |
| Webpage traffic sources | Where the arrivals on one webpage came from, last 90 days | Webpage > Traffic sources tab |
| Phase 0: data fixes | Smartphones and phablets count as mobile; session duration caps idle time at 30 minutes; `pagespeed` props renamed to `real_user_speed` | SEO dashboard figures |
| 1.1 Search Console history | 16 months of clicks, impressions, CTR and position, fetched daily, per website, page and query | SEO dashboard: Google Search card, Search queries and Low CTR queries tabs, search columns on Webpages; Webpage > Performance tab |
| 1.2 Site Audit | Weekly crawl of every live website, 23 issue types, health score and trend, pages per issue | SEO > Site audit |
| 1.3 404 log | Paths that returned 404, by hits, with a Create redirect action | SEO > Missing pages |
| Tracked keywords and competitors | The team sets the keyword list (country, language, device, frequency) and competitor domains per shop, ready for rank tracking | SEO > Keywords |
| Weekly external link check | Rechecks the status of every external link | Scheduled, Sunday 02:00 UTC |

## Skipped

| Part | Why |
| --- | --- |
| 1.4 PageSpeed lab scores | HELP-3303 (commit `92f37c381f`) replaced the Lighthouse lab test with field data from the Chrome UX Report and our visitors' web vitals, because the lab score sent staff after numbers customers never saw. Page speed stays on Real user speed. The old `pagespeed_*` columns on `webpage_time_series_records` keep their history until the owner of HELP-3303 decides to drop them. |

## Taken out

| Part | Why | To bring it back |
| --- | --- | --- |
| 2.1 Keyword research | Keyword Planner refuses the developer token, which has Explorer access only, and the paid alternative waits on the provider decision. | Get Google Ads Basic access or choose a keyword data provider, then restore the files from commit `f370bbc652`. |

## Waiting on a decision

| Part | Decision needed |
| --- | --- |
| 2.2 Rank tracking | SERP provider and monthly budget. The tracked keyword list and competitors are already set in SEO > Keywords. |
| 3.1 Backlinks, 3.2 Competitor research | Backlink and competitor data provider, ideally the same as the SERP one |
| 3.3 AI visibility | Models and prompts per shop |
| 3.5 Competitor traffic | Apify actor (cheap, against Similarweb's terms) or the Similarweb API (licensed, priced through sales) |
| 3.5 Non-Google demand | Which platforms to collect search signals from |

## Not started

- 3.4 Content help: meta title and description suggestions through the AI gateway. The first Site
  Audit found many product pages with an empty meta description, which is where this would start.
- Bing Webmaster Tools keyword data (3.5), free.
- Comparing Site Audit results with a Semrush Site Audit of the same website, needed before the team
  relies on it.

## After deploying

1. `php artisan migrate`
2. Once, to correct the last 30 days of the dashboard figures:
   `php artisan maintenance:recalculate_website_visitor_durations`, then
   `php artisan websites:redo_time_series --from=<30 days ago> --to=<today>`.
3. Once, to load 16 months of Search Console history: `php artisan search_console:fetch --async`.
4. Add the service account named on the Google Search card as a user on every Search Console
   property that is still missing.

Scheduled from then on: Search Console fetch daily at 02:30 UTC, site audits Sunday 03:00 UTC,
external link check Sunday 02:00 UTC, 404 path pruning daily at 03:50 UTC.

## Configuration

| Variable | Used for | Needed |
| --- | --- | --- |
| `GOOGLE_OAUTH_CLIENT_SECRET` (or group setting `gcp.oauthClientSecret`) | Search Console | Yes |
| `GOOGLE_CRUX_API_KEY` | Real user speed from the Chrome UX Report (existing feature) | Yes, for Real user speed |
| `GOOGLE_ADS_DEVELOPER_TOKEN` | Keyword research, if 2.1 comes back | Only with Basic access |
| `GOOGLE_PAGESPEED_API_KEY` | Nothing; 1.4 is skipped | No |
| `OPENROUTER_API_KEY` | AI visibility and content help (Phase 3) | Not yet |
