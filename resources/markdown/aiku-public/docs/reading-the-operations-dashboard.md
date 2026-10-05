---
title: Reading the Operations dashboard
summary: The warehouse view of the dashboard — what needs attention now, where every delivery note is, how fast orders leave, who is picking and packing, what is arriving and what it will release. It refreshes itself every minute.
date: 2026-10-05
tags: warehouse, dispatch, goods in, picking, packing, dashboard
category: warehouse
---

<aside class="tldr">
Open <b>Dashboard</b> and pick the <b>Operations (In/Out)</b> tab. Goods in, goods out and fulfilment staff land on it straight away. Read the coloured strip at the top first: every tile counts something that needs a person now, and is grey when there is nothing to do. Under it, the pipeline shows where every delivery note is, then dispatch speed, the picking and packing team, goods in, stock and returns, and a small sales card at the bottom. Every number is a link to the list behind it.
</aside>

Open it at **Dashboard → Operations (In/Out)**. If your job is in goods in, goods out or fulfilment, the dashboard opens on this tab. Whichever tab you pick last is the one you see next time, so you can switch back to **Sales** or **Stock** for good if you prefer.

## Filters and time

Two filters sit at the top:

- **Warehouse** — one warehouse (with its organisation) or all of them. You only see the warehouses you work in.
- **Channel** — Trade, Retail, Dropshipping, Marketplace or Fulfilment client.

Your choice is remembered. To the right, each warehouse shows its **local time**. "Today", "yesterday" and "same day" always mean the warehouse's own calendar, not UTC. The page fetches fresh figures every minute while it is open. **Live** fetches them now.

With **all warehouses** selected, each tile shows a short code per warehouse under the total (for example *ED 7 · PAR 20*). Click a code to open that warehouse's list. With a single warehouse selected, the big number itself is the link.

## Needs attention now

Eight tiles. A tile is **grey at zero**, **amber** when it needs looking at and **red** only when a limit is broken. The ? on each tile gives the exact definition.

- **Urgent queue not picked** — premium dispatch delivery notes that nobody has started picking yet. Red when the oldest has waited more than an hour.
- **At risk of missing collection** — needs each carrier's collection times per warehouse. These are not set up yet, so the tile shows a dash.
- **Blocked orders** — delivery notes whose picking stopped, split by why: **Stock** (an item is waiting for the warehouse) or **CS** (an item is waiting for customer service). Amber above 10, red when one has been blocked for more than 24 hours.
- **CS waiting for decision** — delivery notes in picking or blocked with an item waiting for customer service, with the age of the oldest. Red after 24 hours. Opens the list of items waiting for customer service.
- **Out of stock on open orders** — SKOs with no stock in any location that are still to be picked on an open delivery note, and how many delivery notes they hold up.
- **Replenishment needed** — picking locations below their minimum while another location still has stock.
- **Overdue deliveries** — inbound stock deliveries not yet arrived whose expected date has passed. The expected date is the delivery's own date, or the purchase order's if the delivery has none.
- **Stock errors** — locations with negative stock.

## Order pipeline

Every open delivery note, by stage:

- **To assign** — in the warehouse, not given to a picker.
- **Queued** — given to a picker, not started.
- **Picking** — being picked.
- **Blocked** — picking stopped (see above).
- **Packing** — picked, or being packed.
- **Packed** — packed, not yet invoiced and finalised.
- **Waiting for dispatch** — invoiced and ready, waiting for the carrier.
- **Dispatched today**.

Under each count you see how long the **oldest** one has been in that stage. If you can see sales figures, you also see the order value. Replacements are counted too, and shown separately, because they are warehouse work with no order value.

This tab counts **delivery notes**, the thing the warehouse works on. The Sales tab counts **orders**, so the two differ by the replacements and by orders with more than one delivery note. The **Stock** tab shows stock levels only; what the warehouse is working on lives here.

**Why the delivery notes are waiting** splits the ones to assign or queued by the stock on the shelves right now: **pickable now** (everything is there), **partly pickable** or **awaiting stock** (nothing is there). Other orders asking for the same stock are not taken off, so treat "pickable" as "worth sending a picker".

## Next collections and dispatched today

The carrier countdown needs collection times per carrier and warehouse. Until those are set up, this card says so.

**Dispatched today, by this time** compares today with yesterday and with the same weekday last week, each counted only up to the current time of day. It shows delivery notes, parcels and lines.

## Time to dispatch

The time from a delivery note reaching the warehouse to its dispatch, for orders only (not replacements). Choose **Today**, **Last 7 days** or **Last 30 days**.

- **Median** and **90th percentile**. With all warehouses selected, the 90th percentile is the slowest warehouse's.
- **Same day** — the share dispatched on the same calendar day it arrived.
- **Within SLA** — needs the service level per channel and fulfilment client, which is not set yet.

**Open delivery notes by age** shows everything still open in four buckets: under 4 hours, 4 to 24 hours, 1 to 2 days and over 2 days.

## Pickers and packers

Team figures only. Figures per person wait for HR approval in Slovakia and Spain.

- **Pickers** — every picked line is recorded with its picker and time, so the card counts lines picked today, **per person per hour** (each person's hours run from their first to their last pick), the last hour and **short picks** (lines marked as not picked).
- **Packers** — delivery notes packed today, per person per hour and the last hour.

**Active now** means someone recorded work in the last 15 minutes. **Idle** means someone worked in the last hour but not in the last 15 minutes.

## Goods in

Counts of deliveries **on the way**, **overdue**, **to book in** (arrived or checked) and **booking in**. Then come **dock to stock**, the median and 90th percentile time from arrival to booked in over the last 90 days, and how many open deliveries have **no ETA** or **no purchase order**.

The table lists open deliveries with the overdue ones first. **Releases** is the number of open delivery notes waiting for stock that the delivery carries. Put away first the delivery that releases the most orders. Click a supplier to open the delivery.

## Stock and locations

**Empty locations** out of all locations, locations with stock **not counted in 90 days**, **negative stock** and **replenishments due**. Below these, **Out of stock with open orders** lists the SKOs holding up the most delivery notes, with the inbound delivery's expected date if one is on the way.

Location capacity (how full a zone is) needs capacities on the locations, which are not recorded yet.

## Returns

**To process** — returns received in the warehouse that are not processed yet, with the oldest. **Customer returns received** and expected. Then the units processed this month (restocked, damaged, not returned) and the most common return reasons.

## Sales by organisation

A collapsible card at the bottom with one row per organisation, as a workload signal:

- **Orders in today** with the last seven days as a small bar line.
- **vs same weekday LY** — against the same weekday last year, up to the same time.
- The number turns **red** when today is more than 25% above the average of the last four same weekdays: a sign to add hands.

If you can see sales figures, you also see **value in today**, **in warehouse pipeline**, **month to date** (invoiced) and **% of month target**, each organisation in its own currency, with a group row in pounds.
