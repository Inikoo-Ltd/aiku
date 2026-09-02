---
title: Following an order from basket to dispatch
summary: See the whole journey a sales order takes in aiku, from a customer's basket through picking and packing to invoicing and dispatch, and where to check on it at each step.
date: 2026-09-01
tags: orders, orders lifecycle
category: orders
help_routes: grp.org.shops.show.ordering.orders
---

<aside class="tldr">
Every sales order moves through a fixed set of states, from <b>In Basket</b> to <b>Dispatched</b>. You can follow any order's progress from the shop's <b>Orders</b> screen: open the order and you will see which state it is in, its transactions, its delivery notes and, once it is invoiced, its invoice. This article walks through that journey in order.
</aside>

## Where an order starts: In Basket

A customer builds an order by adding products — aiku calls each product line a transaction. While they are still adding and changing things, the order sits in the **In Basket** state. Nothing has been sent anywhere yet, and the customer can keep editing freely.

## Submitted

When the customer checks out, the order becomes **Submitted**. An order can only be submitted once — trying to submit it again is blocked, and so is submitting an order that has no transactions on it at all.

If the order is already paid, or it is a cash-on-delivery order, aiku sends it straight to the warehouse the moment it is submitted. If payment is still outstanding, the order waits in **Submitted** until payment comes in.

## In Warehouse, then picking

Once an order is sent to the warehouse, aiku creates a **delivery note** for it and the order moves to **In Warehouse**. This is the order's queue position — it is waiting for a picker to start.

From there the order follows the delivery note through the warehouse floor:

- **Handling** — a picker is actively picking the order.
- **Waiting** (internally called handling blocked) — picking has stalled, for example because stock cannot be found.
- **Picked** — every line has been picked.
- **Packing**, then **Packed** — the order is packed and ready to leave.

## Finalized: this is when the invoice is created

When the delivery note is finalised, aiku moves the order to **Finalized** and, in the same step, generates its invoice. An order cannot be finalised twice — if it already has an invoice, finalising it again is blocked. This is the point where the sale becomes a real invoice in Accounting, and you will see it appear on the order's **Invoices** tab.

## Dispatched

When the delivery note is actually dispatched from the warehouse, the order's state changes to **Dispatched**. That is the end of the normal journey — the goods have left and the sale is invoiced.

## Cancelled

An order can be cancelled instead of following the states above — for example if a shop closes before the order was actioned. A cancelled order stops moving through the pipeline.

## Changing an address after the order is submitted

When an order is submitted, aiku takes a copy of the customer's billing and delivery addresses and keeps them with the order. Tax and shipping were calculated on those addresses, so the order does not change on its own afterwards. Editing the address on the customer's record updates baskets that are still open, but it never touches an order that has already been submitted.

To change the address on a submitted order, do it on the order itself. Open the order and, next to the address block, click **Edit** to change the delivery address or **Edit billing address** to change the billing one. If the order shows a single combined address, both links sit under it. The change is applied to the order and its delivery note, and the totals are recalculated if the tax treatment changes. This is possible right up until the order is dispatched.

Once the order is dispatched, the order's addresses are fixed for good. If the customer then needs a different address on their invoice, correct it on the invoice: open the invoice from the order's **Invoices** tab and use the pencil next to the address.

## Finding an order and checking its progress

Open a shop and go to **Orders → Orders** to see every order for that shop. Each row shows the order's state as an icon, its reference, when it was submitted or dispatched, the customer, the payment status, delivery information and the net amount. You can filter the list by state, and search by reference or tracking number.

Open any order to see its full record. The page shows the order's reference as the title, with its current state shown next to it, and a row of tabs:

- **Transactions** — the product lines on the order.
- **Marketing** — where the order came from.
- **Payments** — payments taken against the order.
- **Invoices** — the invoice generated when the order was finalised.
- **Delivery notes** — the warehouse paperwork carrying the order through picking, packing and dispatch.
- **Returns** — any returns linked to the order.
- **Attachments**.
- **Dispatched emails** — the emails aiku has sent about this order.
- **History** — a record of everything that has happened to the order.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>See every order:</b> your shop → <b>Orders</b> (top menu) → <b>Orders</b> tab. Filter by state or search by reference.</li>
<li><b>Check one order's progress:</b> click into it from the list — the state shows next to its reference, and the <b>Delivery notes</b> and <b>Invoices</b> tabs show what has happened downstream.</li>
<li><b>See what is waiting to be worked:</b> your shop → <b>Orders</b> (top menu) → <b>Backlog</b> tab.</li>
<li><b>Change an address on a submitted order:</b> open the order → <b>Edit</b> or <b>Edit billing address</b> under the address block, until it is dispatched.</li>
<li><b>Correct the address on an issued invoice:</b> open the order → <b>Invoices</b> tab → open the invoice → pencil next to the address.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permissions you need</strong>
You need view access to Orders for that shop, or view access to Accounting for the organisation, to see this list and its orders.
</aside>
