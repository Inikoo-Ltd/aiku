# Budget

What the data providers for Phase 2 and 3 would cost, set against the Semrush subscription the plan
replaces. Written on 8 October 2026. Prices are list prices on that date, read from search results
quoting the providers' pricing pages; confirm them in the provider dashboards before the budget is
approved.

## The goal

Semrush costs about $800 a month today and covers every feature in the plan. The point of moving
into Aiku is to spend less, so the providers must stay well under that, and the build and upkeep of
Phase 2 and 3 have to be worth the difference.

## Providers

**DataForSEO** covers almost everything. It is pay as you go: a minimum deposit (reported as $50)
and, since 1 July 2026, no monthly commitment on any API, Backlinks included.

| Plan part | DataForSEO API | Price |
| --- | --- | --- |
| 2.1 Keyword volumes and trends | Keywords Data, Google Ads | $0.05 per task of up to 1,000 keywords (standard queue), $0.075 live |
| 2.1 Keyword ideas, difficulty, intent | Labs | $0.012 per task + $0.00012 per row |
| 2.2 Rank tracking, SERP features, AI Overviews, competitor positions | SERP API, Google Organic | $0.0006 per page of 10 results (standard queue), $0.002 live |
| 3.1 Backlinks, referring domains, new and lost links, backlink gap | Backlinks | $0.024 per request + $0.000036 per row |
| 3.2 Competitor ranked keywords, organic competitors, keyword gap | Labs | As above |
| 3.3 AI visibility | AI Optimization: LLM Responses, LLM Mentions | Responses: $0.0006 + the model's own cost. Mentions: $0.10 per request + $0.001 per row |

- Keyword volumes come from Google Ads data without our developer token, so 2.1 does not wait for
  Google Ads Basic access.
- Competitor positions come in the same SERP response as ours and cost nothing extra.
- Site Audit and page speed need no provider: our crawler and CrUX already do them.

**Apify** fills the two gaps DataForSEO cannot:

- Total traffic of a competitor's domain, all channels. DataForSEO only estimates search traffic
  from rankings. A Similarweb actor such as `fetch_cat/similarweb-traffic-scraper` costs $1.50 per
  1,000 domains, with the terms risk described in
  [implementation-plan.md](implementation-plan.md#35-apify-for-competitor-traffic-and-non-google-search-demand).
- Search suggestions on platforms that publish no volume and DataForSEO does not cover, such as
  TikTok, Pinterest, Etsy and Instagram. For Bing use the free Bing Webmaster Tools API, and check
  DataForSEO's Bing and Amazon keyword data before using Apify for those.

Apify plans: Free ($5 of usage a month), Starter ($29, all of it prepaid usage).

## Scenarios

For the 29 live websites.

| Part | Lean | Standard | Assumptions (lean / standard) |
| --- | --- | --- | --- |
| Rank tracking | $15 | $90 | 100 / 200 keywords per site, desktop / desktop and mobile, weekly, top 20 / top 30 |
| Keyword research and monthly volume refresh | $5 | $20 | Team searches and saved keywords refreshed monthly |
| Backlinks | $30 | $100 | Summaries weekly for our sites and about 100 competitor domains; our own link lists monthly / weekly |
| Competitor research and gap tools | $10 | $35 | Ranked keywords per competitor monthly, gap lookups as needed |
| AI visibility | $70 | $300 | 10 brands × 20 prompts / 29 shops × 30 prompts, 4 models, weekly, about $0.02 per answer with web search |
| LLM Mentions | $10 | $15 | A few lookups per brand per month |
| DataForSEO | ~$140 | ~$560 | |
| Apify | $0 | $29 | |
| **Total per month** | **~$140** | **~$590** | |

Plus about $50 once, to backfill volumes and classify intent for keywords that did not come from
Labs.

The standard scenario saves only about $210 a month against Semrush, too little for the build and
upkeep. Copying Semrush exactly costs more than Semrush: Position Tracking checks the top 100 daily,
and 5,000 keywords checked that way through DataForSEO cost about $900 a month on their own.

## Proposal: a cap of about $250 a month

| Part | Settings | Per month |
| --- | --- | --- |
| Rank tracking | 5,000 keywords weekly (top 30), plus 500 key keywords daily (top 20) | ~$60 |
| Keyword research | | ~$5 |
| Backlinks | Weekly summaries, our own link lists monthly | ~$50 |
| Competitor and gap tools | | ~$25 |
| AI visibility | Per brand (10 brands), not per shop | ~$80 |
| Apify | | $0 to $29 |
| **Total** | | **~$250** |

That saves about $550 a month, about $6,600 a year, against Semrush. Aiku also keeps what Semrush
cannot give: SEO data joined to webpages, orders and conversions, history kept for as long as we
want, and no price per user.

## What drives the cost

- **Rank tracking depth and frequency.** DataForSEO bills each page of 10 results, so the top 100
  costs ten times the top 10, and daily costs about seven times weekly. Keep daily checks to a short
  list of keywords that earn money.
- **AI visibility.** Most of it is the models' own cost for tokens and web search, paid whether the
  prompts go through DataForSEO or our OpenRouter gateway. The per answer figure above is an
  estimate.
- **Domains typed into the comparison and gap tools.** Each new domain is a fetch; the 30 day cache
  and the budget cap in the plan keep this bounded.

The per-run budget cap and the `seo_api_requests` log from the
[ground rules](implementation-plan.md#ground-rules) enforce the cap and show the month's spend.

## Before approving

- Find out what the $800 buys: the plan, the number of users and the add-ons (for example Trends
  for competitor traffic). Money spent on seats or add-ons the team rarely uses is extra saving.
- Confirm the prices above in the DataForSEO and Apify dashboards.
- Semrush and Aiku run side by side during the build and for one billing cycle after Phase 3, so
  both are paid for that time.
