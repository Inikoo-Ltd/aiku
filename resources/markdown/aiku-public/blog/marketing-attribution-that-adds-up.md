---
title: Marketing attribution that adds up to one
summary: We had an attribution feature for years and it had credited 0.08% of customers. Here is what was wrong, what we rebuilt, and the one invariant we now check after every change.
date: 2026-08-08
tags: marketing, attribution, postgres
---

For a long time our marketing attribution looked finished. There was a table of traffic sources, a cookie that captured click ids, a dashboard. When we finally looked at the numbers it had touched 501 customers out of six hundred thousand, zero orders and zero campaigns. It had never worked.

The cause was boring: the capture cookie was set on a path that the registration form never read. Nothing errored, so nothing was noticed. The lesson we took was not "fix the cookie" but "a feature that cannot report on its own health is not finished". So the rebuild started from the reporting end.

## What a touch is

A **traffic source** is a channel plus a reference: `google-ads` + a campaign id, `newsletter` + the mailshot code, `referral` + the referring host, `organic-search` + the engine. Twenty channels today. Every visit that carries an identifiable source writes a *touch* against the session; when a session becomes a customer, the touches move to the customer.

A customer can have many touches, and money has to be split between them. We store a `share` per customer per source and the rule is simple to state:

> For every customer, the shares must sum to exactly 1.00.

That sentence is the whole design. Multi-touch splitting, attribution windows, overrides per shop — they are all just different ways of producing shares that add up to one.

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
