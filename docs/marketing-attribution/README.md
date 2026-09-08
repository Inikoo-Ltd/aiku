# Marketing attribution

Which marketing channel earned which sale, and what it cost to earn it. Live in production since
7 August 2026.

| Document | Audience |
| --- | --- |
| [reading-the-numbers.md](reading-the-numbers.md) | Marketing and management. What the figures mean, and why revenue looks low. |
| [engineering.md](engineering.md) | Engineers. How it works, what to run, what not to break. |
| [utm-campaign-naming.md](utm-campaign-naming.md) | Whoever builds ad links. The naming standard and its one hard constraint. |
| [troubleshooting.md](troubleshooting.md) | Symptom → cause → fix, plus the rollback note. |

Advertising spend is imported separately — see [../google-ads-cost-ingestion/README.md](../google-ads-cost-ingestion/README.md).

## Where to look in the app

- **Marketing dashboard** — `/org/{organisation}/shops/{shop}/marketing`. Period-scoped, defaults to the last 30 days.
- **Traffic sources listing** — `/org/{organisation}/shops/{shop}/marketing/traffic-sources`. All-time.
- **Customer journey** — the marketing tab of any customer page.

## Status on 7 August 2026

Verified against production on the day of writing:

- 331 customers of 610,019 carry touch data. The system only records touches from the day it went live, so this grows forward and cannot be backfilled.
- 39 campaigns: 1 Google Ads campaign id, 38 mailshots.
- **0 rows of advertising spend imported.** Every ROAS and CAC therefore shows a dash. Nothing is wrong; nothing has been imported yet.
- Attributed revenue is about €1,505 across four shop/channel pairs. That is the honest post-touch figure — see [reading-the-numbers.md](reading-the-numbers.md).
