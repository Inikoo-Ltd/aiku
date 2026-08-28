---
title: Marketing attribution that adds up to one
summary: We had an attribution feature for years and it had credited 0.08% of customers. Here is what was wrong, what we rebuilt — twenty channels, three email channels, ROAS and CAC with real costs, a per-customer journey — and the one invariant we now check after every change.
date: 2026-08-08
tags: marketing, attribution, postgres
---

<aside class="tldr"><strong>TL;DR</strong>The old attribution feature had touched 501 of 600,000 customers because a capture cookie sat on a path the registration form never read — and nothing errored, so nobody noticed. The rebuild starts from one invariant: every customer's touch shares must sum to exactly 1.00, checked by SQL after any change. It now tracks twenty channels (three separate email ones), revenue by order date at group/organisation/shop level, ROAS and CAC with real ad-spend costs, per-customer journey timelines, and a data-quality tab that surfaces gaps before a dashboard number looks wrong.</aside>

For a long time our marketing attribution looked finished. There was a table of traffic sources, a cookie that captured click ids, a dashboard. When we finally looked at the numbers it had touched 501 customers out of six hundred thousand, zero orders and zero campaigns. It had never worked.

The cause was boring: the capture cookie was set on a path that the registration form never read. Nothing errored, so nothing was noticed. The lesson we took was not "fix the cookie" but "a feature that cannot report on its own health is not finished". So the rebuild started from the reporting end.

## What a touch is

A **traffic source** is a channel plus a reference: `google-ads` + a campaign id, `newsletter` + the mailshot code, `referral` + the referring host, `organic-search` + the engine. Twenty channels today. Every visit that carries an identifiable source writes a *touch* against the session; when a session becomes a customer, the touches move to the customer.

A customer can have many touches, and money has to be split between them. We store a `share` per customer per source and the rule is simple to state:

> For every customer, the shares must sum to exactly 1.00.

That sentence is the whole design. Multi-touch splitting, attribution windows, overrides per shop — they are all just different ways of producing shares that add up to one.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>The traffic source model is <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Models/CRM/TrafficSource.php">app/Models/CRM/TrafficSource.php</a>.</li>
<li>Shop-level dashboard aggregation is built by <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/CRM/TrafficSource/GetShopMarketingOverview.php">app/Actions/CRM/TrafficSource/GetShopMarketingOverview.php</a>, with the group/organisation roll-up in <code>app/Actions/CRM/TrafficSource/GetAggregatedMarketingOverview.php</code> and per-shop windows in <code>app/Actions/CRM/TrafficSource/WithAttributionWindow.php</code>.</li>
<li>The repair path for referrer/campaign mismatches is <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/CRM/TrafficSource/RepairHostReferencedCampaignAttribution.php">app/Actions/CRM/TrafficSource/RepairHostReferencedCampaignAttribution.php</a>.</li>
</ul></aside>

## Three email channels, not one

The first draft had one `email` channel. Averaging a newsletter, a promotional mailshot and an automatic reorder reminder into one line hid which of them worked. They are different instruments with different costs and very different unsubscribe rates, so they became three channels: `newsletter`, `marketing-mailshot` and `email-automated`. Each still carries the outbox or mailshot code as its reference, so a channel breaks down per send underneath.

Nineteen rows is a list nobody reads, so channels also **group** into Paid ads / Organic / Email / Other with subtotals and a collapse toggle. Four groups is the question people arrive with; the rows are there when they want to go deeper.

## Revenue follows the order date

Attributed revenue is measured from invoices, by order date, in three currencies at three levels:

| Level | Currency | Columns |
|---|---|---|
| Group | group | `grp_net_amount` |
| Organisation | organisation | `org_net_amount` |
| Shop | shop | `net_amount` |

Organisation and group dashboards are aggregates for management, never a repeat of the level below. The group level deliberately stops at channels, because a campaign belongs to one shop; the drill-down continues there.

## What the dashboard actually shows

Attribution is only worth doing if someone can act on it before lunch. The shop marketing dashboard is three tabs:

**Dashboard** — channels grouped into Paid / Organic / Email / Other, each row with visits, registrations, orders, attributed revenue, cost, and the two figures people came for: **ROAS** (revenue over spend) and **CAC** (spend over new customers). Costs come in two ways: a spreadsheet import for anything, and automated daily ingestion of Google Ads spend per campaign, so the paid rows are rarely stale. Every date range the rest of the system understands works here too — yesterday, last week, month to date, a year — with the previous period alongside.

**Offer performance** — the same attribution applied to promotions: which offers were live on the orders a channel brought in, and what the lift looked like against orders with no offer. This is where "the newsletter worked" turns into "the newsletter worked *because of the 10% on candles*".

**Data quality** — below.

Behind the tables, every customer has a **journey timeline**: each touch in order, with its channel, campaign reference and the share it ended up with. When a number on the dashboard looks wrong, the timeline is where the argument is settled, customer by customer.

Attribution windows are set per shop with a sensible default; a shop that overrides the window is evaluated with its own window even inside the group aggregate. The group and organisation dashboards are aggregates of the shops and link down to them; they do not repeat the shop view with bigger numbers.

## The data quality tab

This is the part we should have built first. On every shop's marketing dashboard there is a third tab that answers:

- what percentage of registrations have no attribution at all,
- which customers have shares that do not sum to one,
- which channels are missing from this shop, and which have never been credited,
- campaign references that matched nothing,
- the live capture counters and the list of rejected referrers.

A command nobody runs answers nothing. A tab that managers look at once a week catches a cookie bug in a day.

## The invariant, as SQL

We run this after any change that touches shares:

```sql
WITH per_customer AS (
  SELECT model_id, round(sum(share), 2) AS total
  FROM model_has_traffic_sources
  WHERE model_type = 'Customer'
  GROUP BY model_id
)
SELECT count(*) FROM per_customer WHERE total <> 1.00;
```

The expected answer is zero. It has been zero since the day it went live.

<aside class="tldr bottom"><strong>In one paragraph</strong>A feature that touched 501 customers by silent failure was rebuilt around a single checkable invariant — shares sum to 1.00 — and everything from three email channels to the data-quality tab exists so that invariant, and the numbers built on it, can be trusted without waiting for someone to notice a broken cookie.</aside>
