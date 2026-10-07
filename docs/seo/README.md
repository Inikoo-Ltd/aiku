# SEO in Aiku

The plan to move the SEO work the team does in Semrush into Aiku, so the subscription can be
cancelled. Written on 7 October 2026. Nothing in the plan is built yet apart from what the
"Where we start from" section lists.

| Document | Audience |
| --- | --- |
| [implementation-plan.md](implementation-plan.md) | Engineers. Data sources, tables, jobs, screens and the order to build them in. |
| [status.md](status.md) | Everyone. What is done, skipped, taken out or waiting on a decision, and the steps after deploying. |

## What the team uses Semrush for

The team uses every Semrush feature that the plan covers, up to and including Phase 3. Aiku has
to replace all of them before the subscription goes.

| Semrush feature | Replaced in | Where the data comes from |
| --- | --- | --- |
| Site Audit | Phase 1 | Our own crawler |
| Organic Research (our own sites) | Phase 1 | Google Search Console |
| Page speed and Core Web Vitals | Already in Aiku | CrUX and our visitors' web vitals (HELP-3303 replaced the lab test) |
| Keyword Overview, Keyword Magic Tool | Phase 2 | Google Ads Keyword Planner, through the Google Ads connection shops already have |
| Position Tracking | Phase 2 | A SERP data provider, plus Search Console for the positions Google reports itself |
| Backlink Analytics, Backlink Gap | Phase 3 | A backlink data provider |
| Domain Overview, Organic Research (competitors), Keyword Gap | Phase 3 | A competitor data provider |
| Traffic Analytics (competitor domains) | Phase 3 | Similarweb estimates, through an Apify actor or the Similarweb API |
| AI visibility (ChatGPT, Gemini, Perplexity, AI Overviews) | Phase 3 | Prompts sent to the models through our AI gateway, and AI Overviews from the SERP provider |

Two things Semrush shows cannot be rebuilt from our own data or from the SEO providers above: total
traffic of a competitor's domain (Semrush gets it from clickstream panels) and search volumes
outside Google. Apify actors can fill both, partly. What they return and what to watch out for is
in [implementation-plan.md](implementation-plan.md#35-apify-for-competitor-traffic-and-non-google-search-demand).

## Where we start from

Already in Aiku and used by the plan:

- First-party traffic per website and per webpage, on the SEO dashboard
  (`/org/{organisation}/shops/{shop}/seo`).
- On-page SEO fields, a preview of them on the webpage page, sitemaps, `robots.txt`, `llms.txt`,
  structured data and 301 redirects.
- Core Web Vitals from CrUX (weekly) and from our own visitors.
- Search Console history stored daily (Phase 1.1).

## The phases

1. **Our own sites, from free and first-party sources.** Search Console history, a real SEO
   crawler and a 404 log. When this phase ships, Site Audit and Organic Research for our own sites
   can be done in Aiku. Page speed is already covered by CrUX and our visitors' web vitals.
2. **Keywords.** Daily or weekly rank tracking for a chosen list of keywords per shop, with
   competitor positions read from the same results. Keyword research is taken out until Google Ads
   Basic access or a keyword data provider is in place.
3. **The outside world.** Backlinks, competitor domains, keyword gaps and AI visibility.

Semrush is cancelled after Phase 3 has run alongside it long enough to compare the figures. The
exit checks are in [implementation-plan.md](implementation-plan.md#cancelling-semrush).
