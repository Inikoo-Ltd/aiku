---
title: Spotting stuck orders in the Orders backlog
summary: The Orders backlog now tells you which orders have sat too long in one stage, which hold lines the picker is waiting for stock on, and who has each order, so you do not need the warehouse to tell you.
date: 2026-10-10
tags: orders, backlog, picking, out of stock, customer service, stuck orders
category: crm
help_routes: grp.org.shops.show.ordering.backlog
---

<aside class="tldr">
Open <b>Orders → Backlog</b>. The four boxes at the top are the orders that need a person: <b>Stuck</b> (in the same stage for more than 2 working days), <b>Waiting for stock</b> (the picker could not find a line and customer service was not told), <b>With customer service</b> (lines sent to you to decide) and <b>Waiting longest</b>. Click a stage name in a box to open exactly those orders. In every stage list, <b>In this stage</b> shows how long the order has been there, oldest first, in red when it is stuck, <b>Progress</b> shows how far picking or packing is, and <b>Picker</b> / <b>Packer</b> show who has the order.
</aside>

Open it at **your shop → Orders → Backlog**. You do not need access to the warehouse's Goods out screens: everything below is in the shop's own backlog.

## The boxes at the top

- **Stuck** — orders that have been in the same stage for more than 2 working days. Under the total, one chip per stage shows how many are stuck there; hover a chip for the age of the oldest one, click it to open the list.
- **Waiting for stock** — orders with at least one line the picker could not find and parked for the warehouse to restock. These are **not** sent to customer service, so until now nobody outside the warehouse saw them. If a line sits here for long, ask the warehouse whether the stock is coming or the line should be sent to you.
- **With customer service** — orders with lines the picker sent to you. **Handle the waiting lines** opens the list where you choose Don't pick, Replace or Send back.
- **Waiting longest** — the five orders stuck for the longest, with their stage. Click the reference to open the order.

When nothing is stuck and no line is waiting, a single green line says so.

Orders that are submitted but unpaid are waiting on the customer, so they are not counted as stuck.

## In each stage list

Click a number in the stage boxes to open that stage. Only the stage you picked is highlighted, and a line above the list says in plain words what it means, for example *Ready to be picked — Sent to the warehouse, picking has not started*.

The columns on the left describe the order; the ones on the right describe how it is moving through the warehouse.

- **Reference, Customer, Net, Submitted** — the order. The small icon in front of the net amount is the payment status; hover it for the detail.
- **In this stage** — how long the order has been in its current stage. Lists open **oldest first**. A red label means stuck. Hover for the exact date.
- **Progress** — a bar, a percentage and the number of lines in brackets. Up to Picked it is picking progress; in Packing it is how many of the picked lines are packed. An amber label next to it means lines waiting for stock or with customer service. Packed and Waiting for dispatch have no bar, because the work is done.
- **Picker / Packer** — who has the order. On *Ready to be picked* the Picker column is empty: nobody has been given the order yet.
- **Delivery** — from Packed onwards: the courier and, underneath in small print, the full tracking number.

An order in Picking with nothing under Progress and Picker has no open delivery note: nobody is working on it. Open the order and check with the warehouse.

## Filtering

Next to Destination and Channel there are now **Payment** (Paid, Unpaid) and **Needs attention** (Stuck, Waiting for stock, With customer service). Each shows its count for the stage you are on. Click one to keep only those orders, click it again to clear.
