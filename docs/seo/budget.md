# Budget

What the data providers for Phase 2 and 3 would cost, set against the Semrush subscription the plan
replaces. Written on 8 October 2026. Prices are list prices on that date, read from search results
quoting the providers' pricing pages; confirm them in the provider dashboards before the budget is
approved.

## The goal

Semrush costs about $899.95 a month today, verified in the marketing team's research of
8 October 2026 ("AIKU SEO Strategy (Research)"), for 27 websites; Aiku has 29 live websites. The
invoice itself is not broken down yet. The research names two compositions close to it, the Business
plan ($499.95, 40 websites and 5,000 tracked keywords) plus three extra users ($100 each), or plus
Traffic & Market ($289). The point of moving into Aiku is to spend less, so the providers must stay
well under that, and the build and upkeep of Phase 2 and 3 have to be worth the difference.

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
| 3.3 AI visibility | AI Optimization: LLM Scraper (ChatGPT as its users see it, per country), LLM Mentions | Scraper: about $0.004 per prompt. Mentions: about $0.10 per request. Both from DataForSEO's examples; its pages disagree on the per-row price of Mentions |
| 3.5 Competitor search traffic, with history back to October 2020 | Labs: bulk traffic estimation | Labs price per task, up to 1,000 domains in one task |

- Keyword volumes come from Google Ads data without our developer token, so 2.1 does not wait for
  Google Ads Basic access.
- Competitor positions come in the same SERP response as ours and cost nothing extra.
- Site Audit and page speed need no provider: our crawler and CrUX already do them.

No other provider. On 9 October 2026 the team chose DataForSEO for AI visibility and competitor
traffic instead of the AI gateway and Apify: total traffic of a competitor across all channels
(only Similarweb has it, through a licensed API priced by sales or Apify actors that break its
terms) is not rebuilt, and platforms that publish no search volume (TikTok, Pinterest, Etsy) are
not covered.

## Scenarios

For the 29 live websites.

| Part | Lean | Standard | Assumptions (lean / standard) |
| --- | --- | --- | --- |
| Rank tracking | $15 | $90 | 100 / 200 keywords per site, desktop / desktop and mobile, weekly, top 20 / top 30 |
| Keyword research and monthly volume refresh | $5 | $20 | Team searches and saved keywords refreshed monthly |
| Backlinks | $30 | $100 | Summaries weekly for our sites and about 100 competitor domains; our own link lists monthly / weekly |
| Competitor research and gap tools | $10 | $35 | Ranked keywords per competitor monthly, gap lookups as needed |
| AI visibility, own prompts | $4 | $15 | 10 brands × 20 prompts / 29 shops × 30 prompts, ChatGPT through the LLM Scraper, weekly |
| AI visibility, LLM Mentions | $6 | $16 | Monthly for our domains and their competitors, Google AI Overview per country and ChatGPT (United States only) |
| Competitor search traffic | $1 | $1 | Monthly, all domains in one or two requests |
| Content help | $1 | $4 | Up to 30 pages per website a week through the AI gateway |
| **Total per month** | **~$70** | **~$280** | |

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
| AI visibility | Per brand (10 brands): own prompts through the LLM Scraper, LLM Mentions monthly | ~$15 |
| Competitor search traffic and content help | | ~$5 |
| **Expected spend** | | **~$160** |
| **Cap** | One budget for all SEO APIs, `SEO_API_MONTHLY_BUDGET` | **$250** |

Even at the cap that saves about $650 a month, about $7,800 a year, against the $899.95 Semrush
bill; at the expected spend about $740 a month. If the bill includes Traffic & Market and the team
keeps it for competitor traffic from all channels, the saving is $289 a month less. Aiku also keeps what Semrush
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

The cap is one budget for all SEO APIs together (DataForSEO and the AI gateway), not one per
provider or feature. `SeoApiBudget` enforces it from the `seo_api_requests` log in the
[ground rules](implementation-plan.md#ground-rules), and the
[API usage page](implementation-plan.md#api-usage) shows the month's spend and sets the cap.

## Before approving

- Find out what the $899.95 buys (step 1 of the pilot in
  [implementation-plan.md](implementation-plan.md#cancelling-semrush)): the plan, the number of
  users, the add-ons, and the tracked keywords with their devices and locations. Money spent on
  seats or add-ons the team rarely uses is extra saving, and Traffic & Market is the one add-on
  Aiku does not replace.
- Confirm the prices above in the DataForSEO dashboard, those of the AI Optimization API first.
- Semrush and Aiku run side by side during the build and for one billing cycle after Phase 3, so
  both are paid for that time.
