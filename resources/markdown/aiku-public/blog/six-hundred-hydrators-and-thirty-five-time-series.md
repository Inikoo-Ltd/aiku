---
title: Six hundred hydrators and thirty‑five time series
summary: Every count, total and trend in aiku is precomputed — by ~650 small "hydrator" jobs that run at the point of change and a nightly sweep that catches what they missed, into ~150 stats tables and 35 partitioned time series at five frequencies. Why we chose that over computing on read, the bug where a single‑day redo flattened a whole month, and the index traps in partitioned tables.
date: 2026-07-30
tags: postgres, architecture, performance, time-series
---

<aside class="tldr"><strong>TL;DR</strong>aiku precomputes every count and trend with ~650 <code>hydrator</code> jobs that run at the point of change (plus a nightly sweep) into ~150 stats tables and 35 partitioned time series at five frequencies. A bug where windows were stored as whole periods once let a single day's redo flatten a month; the fix expands any window to full periods before aggregating. Partitioned tables also hid index traps that pinned a replica's CPU until found.</aside>

Open any list in aiku — shops, customers, products, warehouses — and every row carries numbers: orders this month, sales year‑to‑date, stock value, registrations, a Δ against last year. Those numbers are never computed when the page loads. They are read from a stats row or a time‑series record that was written earlier by something we call a **hydrator**.

There are about 650 of them. This note is about why, and what that choice costs.

## Compute on write, read for free

The alternative — aggregate on read — is fine for one shop and a thousand orders. It is not fine for a group dashboard that wants thirty shops × twelve months × previous year, over hundreds of millions of rows, in the time it takes a page to paint. So every entity that anyone has ever put a number next to has a stats table: `shop_stats`, `customer_stats`, `warehouse_stats`, `mailshot_stats`… about 150 of them, each a wide row of counters next to its parent.

A hydrator is a small job that recomputes some of those counters from the source rows. `CustomerHydrateInvoices` counts the customer's invoices and their totals and writes them to `customer_stats`. `ShopHydrateCustomers` rolls customers up to the shop. `OrganisationHydrate…` rolls shops up to the organisation, and the group sits on top. Each hydrator knows exactly which columns it owns.

## At the point of change, not at night

The first rule, learned by being wrong: **a hydrator runs when its inputs change, not only on a schedule.** Early on, a few derived fields were filled only by a 02:30 sweep. The seven products created on a Tuesday morning showed blank margins all day; the data was fine, the job simply had not run. Users read that as a bug, because it is one.

So every write path that touches a hydrator's inputs dispatches it — the store action, the update action, and the *other hydrators* that write those inputs. The jobs are `ShouldBeUnique` keyed on the model id, so a bulk edit that touches a product forty times collapses into one recompute. The nightly sweep still exists; its job is to catch what a forgotten dispatch missed, and a growing gap between "what the sweep changed" and "what should already have been right" is the signal that a write path forgot.

## Time series: the same idea, with a clock

Counters answer "how many now". Dashboards ask "how many in March, and how does that compare". For that there are **35 time series** — per shop, organisation, customer, product, family, master product, outbox, platform, sales channel, invoice category, stock… — each with records at **five frequencies**: daily, weekly, monthly, quarterly, yearly. The records tables are partitioned by frequency, and a record is a period (`from`, `to`) with the metrics that period had: sales in shop, organisation and group currency, invoices, refunds, orders, delivery notes, registrations with and without orders, customers invoiced.

The dashboard's date‑range buttons — yesterday, last week, month to date, one year, all — are lookups over those records, with the previous period alongside for the Δ. Nothing on the dashboard touches `invoices` or `orders` at read time.

## The bug where one day flattened a month

Every processor takes a window `[from, to]` and writes the result. For a long time each one aggregated over *exactly the window it was handed* and stored that as *the whole period's* total. Hand it a single day — which several callers did, on every customer registration, for all five frequencies — and it quietly overwrote that month, that quarter and that year with one day's numbers. The dashboards had been lying a little, every day, in a way that only showed up when someone compared two reports.

The fix was one helper, used everywhere: **expand any incoming window to the full periods it touches** before aggregating. It also made the unique‑job key period‑aligned, so a thousand registrations in an afternoon collapse into one "redo this month" instead of a thousand. Two regression tests — a mid‑period window and a single‑day redo — make sure it stays fixed.

## Partitioned tables have their own index traps

The records tables are large, so the indexes matter, and partitioning changes which ones work. Three traps worth writing down:

- `min("from") / max("to") where series_id = ?` on a partitioned table walked the *date* index across partitions instead of the series index — ten seconds per call, called thousands of times a night. A composite `(series_id, "from")` and `(series_id, "to")` on each records parent fixed it.
- A ten‑million‑row bridge table had no index on the column the nightly job filtered by; thirty‑five seconds per sequential scan, eighteen hundred times a night. One index.
- An interrupted `CREATE INDEX CONCURRENTLY` leaves an *invalid* index behind, and `IF NOT EXISTS` then happily skips creating the real one. Drop it first.

None of this is exotic. All of it pinned a replica's CPU for a day until someone looked.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>Hydrators live per domain, e.g. <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/CRM/Customer/Hydrators/CustomerHydrateInvoices.php">app/Actions/CRM/Customer/Hydrators/CustomerHydrateInvoices.php</a> and <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Catalogue/Shop/Hydrators/ShopHydrateCustomers.php">app/Actions/Catalogue/Shop/Hydrators/ShopHydrateCustomers.php</a>.</li>
<li>Jobs are <code>ShouldBeUnique</code>, keyed on the model id, so repeated writes collapse into one recompute.</li>
<li>Time-series windowing goes through a shared helper, <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Helpers/TimeSeriesPeriodCalculator.php">app/Helpers/TimeSeriesPeriodCalculator.php</a>, which expands an incoming window to the full periods it touches before aggregating.</li>
<li>Per-entity time-series hydrators follow the same naming pattern, e.g. <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Goods/TradeUnit/Hydrators/TradeUnitTimeSeriesHydrateNumberRecords.php">app/Actions/Goods/TradeUnit/Hydrators/TradeUnitTimeSeriesHydrateNumberRecords.php</a>.</li>
</ul></aside>

## The refactor we still owe

The processors loop per period: an upsert plus, for some, four metric queries per period — about 2,200 round‑trips for a year of daily records on one customer. The known fix is to group the metric queries by period and bulk‑upsert, which needs a `(series_id, period, frequency)` unique index on the partitioned tables. It is on the list; it becomes urgent the day historic redos are a daily event rather than a rare one.

## What we would tell our past selves

Decide early that numbers are written, not computed, and put every one of them in a stats row or a time‑series record with a named owner. Run the owner at the point of change *and* at night, and treat a nightly correction as a bug report. Make every window a full period. And when a partitioned table is slow, suspect the index you assumed it would use.

<aside class="tldr bottom"><strong>In one paragraph</strong>Write every number at the point of change instead of computing it on read, give each one a named owner, make every window a full period, and suspect the index a partitioned table assumed it would use.</aside>
