---
title: Planning supplier orders for pre-orders
summary: How the buying team sees every pre-order still waiting for goods, grouped by supplier, compares it with the supplier's minimum order and order-by date, and marks the supplier order as placed or cancels the pre-orders.
date: 2026-09-28
tags: procurement, orders
category: procurement
series: Pre-orders
help_routes: grp.org.procurement.pre_orders
---

<aside class="tldr">
<b>Procurement › Pre-orders</b> lists every customer pre-order that is still waiting for goods, grouped by the supplier of what it waits for. For each supplier you see how many pre-orders there are, the quantity, their sales value and their approximate cost at the supplier, next to the supplier's <b>minimum order</b> and <b>order-by date</b>. When you place the supplier order, mark it with <b>Mark supplier ordered</b>. If the minimum cannot be reached in time, you decide whether to order anyway or cancel them with <b>Cancel all, full refund</b>.
</aside>

## What the page shows

Customers can pre-order products marked **back-order** or **made-to-order** (see [Selling pre-orders](/docs/selling-pre-orders)). Some suppliers are ordered from for each customer order; others only once enough pre-orders are collected to fill a shipment. This page is for grouping them.

Each supplier is one row, with the suppliers whose order-by date comes first at the top:

- **Pre-orders** — how many customer orders are waiting on this supplier.
- **Quantity** — how much of their SKOs is waiting, in SKO units.
- **Sales value** — what the waiting items sell for, in the organisation's currency.
- **At supplier cost (approx.)** — the waiting quantity at the supplier's current price, in the supplier's currency. Next to it is the supplier's **minimum**, green when it is reached and red when it is not.
- **Order by** — the date the supplier order should be placed.

Click a supplier to see each waiting line: the order, the shop, the customer, the product and SKO, the quantity, when it was ordered, when the supplier order was placed and the date it should be dispatched by.

A pre-order waits on the **preferred supplier** of each of its SKOs. When one order waits on two suppliers, it appears under both.

## Setting the minimum and the order-by date

Both are on the supplier: **Minimum order** in its purchasing settings, and **Order by date** and the **pre-order lead time** under **Pre-orders**. The lead time is what customers are told to expect, so keep it realistic: it is the time from the customer ordering to us dispatching.

## Placing the supplier order

When you have ordered from the supplier, press **Mark supplier ordered** on that supplier's row. It marks every pre-order in the group that is not marked yet.

This matters to the customer: a trade customer can cancel a made-to-order item for free **until the supplier order is placed**. From then on, cancelling keeps their deposit. Mark it on the day you order, not before.

## When the minimum is not reached

If the pre-orders do not reach the supplier's minimum by the order-by date, the buying team decides:

- **Order anyway** — place the supplier order and mark it as above.
- **Cancel them** — press **Cancel all, full refund**. Every pre-order in the group is cancelled and every customer gets everything back, deposit included, to their account balance. They are emailed.

You can also cancel a single pre-order from its order page.

## When the goods arrive

You do not need to do anything here: when the stock is booked in, the pre-orders waiting for it take it, oldest first, and they leave this page. The customers are asked for their balance, and each order goes to the warehouse once it is paid.

<aside class="wayfinder">

### Where to click in aiku

- **Open the page** — **Procurement**, then the **Pre-orders** card on the dashboard.
- **See the lines of a supplier** — click the supplier's row.
- **Mark the supplier order as placed** — **Mark supplier ordered** on the supplier's row.
- **Cancel a supplier's pre-orders** — **Cancel all, full refund** on the supplier's row.
- **Set the minimum, order-by date and lead time** — open the supplier, **Edit**: **Minimum order**, then **Pre-orders**.

### Permissions you need

- Seeing the page needs permission to view procurement in the organisation.
- **Mark supplier ordered** and **Cancel all, full refund** need permission to edit procurement.

</aside>
