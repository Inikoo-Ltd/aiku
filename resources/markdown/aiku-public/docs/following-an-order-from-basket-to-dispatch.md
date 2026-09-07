---
title: Following an order from basket to dispatch
summary: See the whole journey a sales order takes in aiku, from a customer's basket through picking and packing to invoicing and dispatch, and where to check on it at each step.
date: 2026-09-02
tags: orders, orders lifecycle
category: orders
help_routes: grp.org.shops.show.ordering.orders
---

<aside class="tldr">
Every order travels the same path, from <b>In Basket</b> all the way to <b>Dispatched</b>. Want to know where one has got to? Open your shop's <b>Orders</b> screen and click into it. You will see the state it is in, what is on it, its delivery notes and — once it has been invoiced — the invoice itself. Here is that journey, step by step.
</aside>

## It starts in the basket

Your customer starts adding products. Each line they add is what aiku calls a transaction. While they are still browsing, adding and changing their mind, the order simply sits in **In Basket**. Nothing has gone anywhere yet, so they can keep tweaking it as much as they like.

## They check out: Submitted

The moment the customer checks out, the order becomes **Submitted**. It can only happen once — try to submit the same order twice and aiku will stop you. It will also stop you submitting an empty order, since there is nothing to send.

What happens next depends on the money. If the order is already paid, or it is cash on delivery, aiku passes it to the warehouse straight away. If you are still waiting for payment, the order sits patiently in **Submitted** until it arrives.

## Off to the warehouse, then picking

Once the order reaches the warehouse, aiku creates a **delivery note** for it and moves the order to **In Warehouse**. Think of this as the queue — the order is lined up, waiting for someone to pick it.

From here, the order follows its delivery note across the warehouse floor:

- **Handling** — someone is picking it right now.
- **Waiting** (you may see it called handling blocked) — picking has stalled, usually because the stock cannot be found.
- **Picked** — everything on the order has been picked.
- **Packing**, then **Packed** — it is boxed up and ready to go.

## Finalized: the moment the invoice appears

When the delivery note is finalised, aiku moves the order to **Finalized** and creates the invoice in the very same step. You cannot do this twice — if an invoice already exists, aiku blocks a second attempt. This is the moment the sale becomes a real invoice in Accounting, and you will find it waiting on the order's **Invoices** tab.

## Dispatched

When the delivery note actually leaves the warehouse, the order becomes **Dispatched**. That is the happy ending: the goods are on their way and the sale is invoiced.

## What if it gets cancelled?

An order does not always make it to the end. It can be cancelled instead — say the shop closes before anyone got round to it. Once cancelled, the order stops moving through the pipeline.

## Changing an address after checkout

When an order is submitted, aiku takes a snapshot of the customer's billing and delivery addresses and keeps it with the order. That is deliberate: tax and shipping were worked out from those addresses, so the order will not quietly change underneath you. Edit the address on the customer's record and you will update their open baskets, but orders that have already been submitted stay exactly as they were.

So if you need to change the address on a submitted order, change it on the order itself. Open the order, look next to the address block and click **Edit** for the delivery address, or **Edit billing address** for the billing one. If the order shows one combined address, both links sit underneath it. aiku applies your change to the order and its delivery note, and recalculates the totals if the tax treatment has changed. You can do this right up until the order is dispatched.

After dispatch, the addresses on the order are locked for good. If the customer then asks for a different address on their invoice, fix it on the invoice instead: open it from the order's **Invoices** tab and use the pencil next to the address.

## Notes on an order, and what prints on the shipping label

Open any order and you will see a row of coloured note boxes at the top. Each one goes somewhere different, so it matters which one you write in. Double-click a box to edit it.

- **Shipping Label (From Customer)** — the text printed on the courier label under the address. Only the first 34 characters fit, so keep it to what the driver needs: "Ring bell at side door", "Open 9-5 Mon-Fri". It normally comes from the customer, who fills it in as *delivery instructions* in their basket, but staff can edit it too.
- **Customer Instructions** — what the customer typed when ordering. Read-only.
- **Public Note** — visible to the customer as well as staff.
- **CRMs Note (Private)** — for staff only, not shown on the delivery note.
- **Warehouse Note (Private)** — for staff only, and printed on the delivery note for the pickers and packers.

Some customers need the same label text on every parcel, typically their opening hours. Do not retype it on each order. Open the customer's record and fill in **Shipping Label (Permanent)** in the notes row at the top. From then on, every new basket that customer opens starts with that text already in its delivery instructions, so the customer sees exactly what will print and can change it for that one order if they need to. If they leave it as it is, that is what goes on the label. Orders that were already in the basket before you set it pick it up at checkout, as long as nobody typed a label note on them.

The customer's own text always wins over the permanent note. That is deliberate: the person receiving the parcel knows best what the driver should read that day.

The same row on the customer record has a **Warehouse Note (Permanent)**, which works the same way for the warehouse note, and a **Warehouse Note (Temporary)**, which is used once, on the next order only.

## Finding an order and checking how it is doing

Open a shop and head to **Orders → Orders** to see everything that shop has taken. Each row tells you the state as an icon, the reference, when it was submitted or dispatched, who the customer is, whether it is paid, the delivery details and the net amount. You can filter by state, and search by reference or tracking number.

Click any order to open its full record. The title is the order's reference, with its current state right beside it, and underneath you get a row of tabs:

- **Transactions** — the product lines on the order.
- **Marketing** — where the order came from.
- **Payments** — what has been paid against it.
- **Invoices** — the invoice created when the order was finalised.
- **Delivery notes** — the warehouse paperwork that carried it through picking, packing and dispatch.
- **Returns** — anything sent back to you.
- **Attachments**.
- **Dispatched emails** — the emails aiku has sent the customer about this order.
- **History** — everything that has ever happened to the order.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>See every order:</b> your shop → <b>Orders</b> (top menu) → <b>Orders</b> tab. Filter by state or search by reference.</li>
<li><b>Check how one order is doing:</b> click into it from the list — the state sits next to its reference, and the <b>Delivery notes</b> and <b>Invoices</b> tabs show what has happened since.</li>
<li><b>See what is still waiting to be worked on:</b> your shop → <b>Orders</b> (top menu) → <b>Backlog</b> tab.</li>
<li><b>Change an address on a submitted order:</b> open the order → <b>Edit</b> or <b>Edit billing address</b> under the address block, any time before it is dispatched.</li>
<li><b>Correct the address on an invoice already issued:</b> open the order → <b>Invoices</b> tab → open the invoice → pencil next to the address.</li>
<li><b>Change what prints on the courier label for one order:</b> open the order → double-click <b>Shipping Label (From Customer)</b> in the notes row → save. First 34 characters print.</li>
<li><b>Set a permanent label note for a customer:</b> your shop → <b>Customers</b> → open the customer → <b>Shipping Label (Permanent)</b> in the notes row at the top. It pre-fills the delivery instructions of every new basket.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permissions you need</strong>
To see this list and the orders on it, you need view access to Orders for that shop, or view access to Accounting for the organisation.
</aside>
