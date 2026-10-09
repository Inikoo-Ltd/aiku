# SEO in Aiku

The plan to move the SEO work the team does in Semrush into Aiku, so the subscription can be
cancelled. Written on 7 October 2026. Nothing in the plan is built yet apart from what the
"Where we start from" section lists.

| Document | Audience |
| --- | --- |
| [implementation-plan.md](implementation-plan.md) | Engineers. Data sources, tables, jobs, screens and the order to build them in. |
| [status.md](status.md) | Everyone. What is done, skipped or not started, and the steps after deploying. |
| [budget.md](budget.md) | Everyone. What the data providers would cost against Semrush, and the proposed monthly cap. |

## What the team uses Semrush for

The team uses every Semrush feature that the plan covers, up to and including Phase 3. Aiku has
to replace all of them before the subscription goes.

| Semrush feature | Replaced in | Where the data comes from |
| --- | --- | --- |
| Site Audit | Phase 1 | Our own crawler |
| Organic Research (our own sites) | Phase 1 | Google Search Console |
| Page speed and Core Web Vitals | Phase 1 | CrUX and our visitors' web vitals (HELP-3303 replaced the lab test) |
| Keyword Overview, Keyword Magic Tool | Phase 2 | DataForSEO Labs (Google Ads volumes, difficulty, intent) |
| Position Tracking | Phase 2 | DataForSEO SERP API, plus Search Console for the positions Google reports itself |
| Keyword intent | Phase 2 | DataForSEO Labs |
| Backlink Analytics (authority, referring domains, new, lost and broken backlinks), Backlink Gap | Phase 3 | DataForSEO Backlinks, joined to our 404 log for broken links |
| Domain Overview, Compare Domains, Organic Research (competitors), Keyword Gap | Phase 3 | DataForSEO Labs |
| Top Pages (our own sites) | Phase 3, traffic part any time | Aiku's tracking and Search Console now; referring domains and AI citations per page with Phase 3 |
| Traffic Analytics (competitor domains) | Phase 3 | DataForSEO search traffic estimates with their history; traffic from other channels is not rebuilt |
| AI visibility (ChatGPT, Gemini, Claude, Perplexity, AI Overviews) | Phase 3 | DataForSEO LLM Scraper (our prompts in ChatGPT as its users see it, per country) and LLM Mentions (share of voice), and AI Overviews from the rank checks |

Two things Semrush shows are not rebuilt: the traffic of a competitor's domain from channels other
than search (Semrush gets it from clickstream panels, and only Similarweb sells the same) and search
volumes on platforms that publish none. See
[implementation-plan.md](implementation-plan.md#35-competitor-traffic-and-non-google-search-demand).

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
   crawler, a 404 log and Core Web Vitals from real visits. When this phase ships, Site Audit and
   Organic Research for our own sites can be done in Aiku.
2. **Keywords.** Keyword research through DataForSEO and daily or weekly rank tracking for a
   chosen list of keywords per shop, with competitor positions read from the same results.
3. **The outside world.** Backlinks, competitor domains, domain comparison, keyword and backlink
   gaps, AI visibility, and top pages with their change against the previous period.

Semrush is cancelled after Phase 3 has run alongside it long enough to compare the figures. The
exit checks are in [implementation-plan.md](implementation-plan.md#cancelling-semrush).
