---
title: 793 tables and counting
summary: Four years, 1,659 migrations, 793 tables, twenty thousand columns, five thousand indexes. How the aiku schema got this big without getting lost — the group/organisation/shop spine on every row, a stats table beside every entity, slugs as public identity, jsonb where the business is still deciding, and the bridge columns that let us migrate off a twenty‑year‑old system one table at a time.
date: 2026-08-17
tags: postgres, architecture, schema, migration
---

The first migration in this repository is dated August 2022. The latest is from last night. In between there are 1,659 of them, and together they describe **793 tables**, about **20,000 columns**, **5,000 indexes** and **2,080 foreign keys**. People who see the number for the first time assume it is a mess. It is not — it is big because the business is big, and it has a small number of rules that have held since the beginning. This note is those rules, and the story of how we got here.

## Where it came from

aiku replaced a system the same team had been running for nearly twenty years. That system had been extraordinary for its time and had become impossible to change. The decision was not to rewrite it in place but to build the new one alongside, pull the data across entity by entity, and switch each shop over when its part of the new system was ready.

That is where the first rule comes from: **115 tables carry a `source_id`**, the identifier of the same row in the legacy system. For two years every fetcher, every reconciliation and every "why does this number differ" question went through that column. Most of those fetchers are retired now; the column stays, because history does not stop being history when the import ends.

## The spine: group → organisation → shop

aiku is multi‑entity from the first table. One **group** holds many **organisations** (legal companies), each with **shops** (a sales channel with its own catalogue, currency and tax), **warehouses**, and so on. So almost every row says where it belongs: `group_id` is on **259 tables**, `organisation_id` on most of them, and `shop_id` or `warehouse_id` on whatever is scoped lower.

That redundancy is deliberate. A customer row "belongs" to a shop, and the shop knows its organisation and group — but the customer row carries `organisation_id` and `group_id` anyway. It makes every query scopeable without a join, it makes permission checks a `where` rather than a traversal, and it makes the group‑level aggregates we come to next possible. The cost is a few extra columns and the discipline of setting them. The discipline is in the store actions, not in people's heads.

## Beside every entity, its stats

This is the rule that most shapes the count. Wherever there is an entity anyone will ever put a number next to, there is a **stats table** beside it: `customers` has `customer_stats`, `shops` has `shop_stats`, `warehouses` has `warehouse_stats`. There are **146** of them. Each is a wide row of counters owned by a named set of hydrators — we wrote about [how those work](/blog/six-hundred-hydrators-and-thirty-five-time-series) — and it is the reason a list of ten thousand customers renders with order counts and lifetime value without a single aggregate at read time.

Some entities have more than one: the counters that change by the second (baskets, picking) live apart from the ones that change by the day (invoices, registrations), so the hot row stays small. Several have sales‑ and crm‑ and stock‑ flavoured companions. And above them all sit the **group aggregators**: the same shapes at organisation and group level, rolled up by the same hydrators, so the group dashboard is a read of one row per organisation, never a sum across shops at request time.

Then the clock: **35 time series**, each with partitioned records at five frequencies, so every one of those counters also has a history.

## Slugs are public identity; ids are private

**121 tables have a `slug`.** Every URL in the staff app, the customer portal and the API uses slugs — `org/acme/shops/uk/customers/000123` — never numeric ids. Slugs are unique within their scope, stable, and human‑readable in a log line. Ids exist for joins and foreign keys and are never shown. The MCP server inherited the same rule, which turned out to matter for keeping models from guessing their way into the wrong shop.

A smaller set of **18 tables carry a `ulid`** for things that are shared in links to people outside the system — conversations, uploads — where guessability matters more than readability.

## Pivots named for what they join

There are **78 `*_has_*` tables**: `model_has_traffic_sources`, `customer_has_dispatched_emails`, `transaction_has_offer_allowances`. The naming is boring on purpose: read the table name and you know both sides and the direction. The bigger ones (emails, events) carry their own `group_id` too, so an archive or a permission scope never has to join through the parent to find out whose rows they are.

## jsonb where the business is still deciding

**205 tables have at least one `jsonb` column**, and it would be easy to read that as laziness. The rule is narrower: jsonb holds the parts of a row whose *shape* the business has not finished deciding, or that differ per instance — `settings`, `data`, per‑channel `platform_data`, the payment provider's raw response, the attribution shares of a touch. When a key in one of those maps starts being queried, filtered or summed, it graduates to a real column with an index and a migration. The history of the schema is partly the history of keys graduating.

## Constraints are logic

2,080 foreign keys and 5,001 indexes are not decoration. Uniqueness that the business means — one shop code per group, one product code per shop — is a unique index, not a validation rule, so the database says no even when the application forgets. Generated columns replaced a family of cached boolean flags that kept [rotting](/blog/one-predicate-to-unblock-a-delivery-note). Partial indexes express business scope ("per‑host uniqueness for these channels, table‑wide for those"). We test against [this real schema](/blog/tests-that-touch-the-real-database), because a schema that enforces the rules is a schema you can trust your tests to.

## Things we would do again, and one we would not

Again: the spine on every row; a stats table per entity from day one; slugs in every URL; naming pivots for what they join; constraints in the database.

Not again: letting a handful of derived values live only in nightly jobs, and letting "cached flag" columns creep in before there was a single owner for each. Both produced the most confusing bugs we have had, and both were fixed by moving the truth to the point of change or into a generated column.

## And counting

A migration lands most days. The next batch is already queued: a customer‑facing MCP, more time‑series dimensions for marketing, the per‑line tax work. The table count will pass eight hundred this autumn and nobody will notice, which is the point — the rules are what keep a schema this size legible, not the size.
