---
title: Ordering from a partner organisation
summary: Why trade between sister organisations uses a shopping list instead of purchase orders, and how the whole loop works from listed need to booked-in stock.
date: 2026-08-31
tags: procurement, warehouse, intercompany
series: Ordering from partners
order: 1
---

<aside class="tldr">
When you buy from a sister organisation you don't raise a purchase order. You add what you need to a shopping list; the selling organisation picks it when they can ship it. From there everything flows on its own: their warehouse picks and packs it, and an incoming stock delivery appears on your side, ready to book in when the goods arrive. If you <em>place</em> these orders, read <a href="/docs/buying-from-a-partner">Buying from a partner</a>; if you <em>fulfil</em> them, read <a href="/docs/fulfilling-partner-orders">Fulfilling partner orders</a>.
</aside>

<figure><img src="/art/docs/draw-partner-shopping.svg" alt="Watercolor sketch: the buyer's shopping list card (Procurement › Partners › Shopping list, with Auto-fill) and the seller's shipping list card with ticked lines and a Send to warehouse button, a dashed arrow between them, and a truck carrying the goods to a box labelled as the incoming stock delivery" width="1200" height="750" loading="eager"><figcaption>You write the list, they pick and ship it, a stock delivery rolls in on your side.</figcaption></figure>

## Why there is no purchase order

A purchase order makes sense with an outside supplier: you commit to quantities, they confirm, and both sides track the same document. Between our own organisations that ceremony gets in the way. The seller knows their own stock better than the buyer does, and forcing the buyer to guess what can be shipped leads to endless amended orders.

So the flow is turned around. The **buyer says what they need**, the **seller decides what ships and when**. Nobody amends anybody's order, because there is no order to amend — just a list of open needs and a stream of shipments against it.

## The loop, end to end

1. The buyer [adds what they need to the shopping list](/docs/buying-from-a-partner) — by hand, from the partner's catalogue, or with an auto-fill proposal.
2. The seller [cherry-picks lines they can ship and sends the shipment to their warehouse](/docs/fulfilling-partner-orders). It is picked, packed and dispatched like any other order.
3. The moment the shipment enters the seller's warehouse, an incoming **stock delivery** appears on the buyer's side. It follows the seller's progress on its own — the seller is the source of truth until the goods arrive.
4. When the goods physically arrive, the buyer receives, checks and places them into locations exactly like any supplier delivery.

## Money, invoices and problems

There are no separate supplier invoices between organisations. The seller's own invoice for the shipment **is** the document, and the incoming stock delivery is linked to it. If something arrives short, damaged or wrong, deal with it *after* you have received the delivery — that is the point where responsibility passes to your side — and any refund or credit is handled against that linked invoice.

## Things worth knowing

- The first time a seller picks for a partner, a customer account named after the buying organisation is created in the seller's shop. That is expected — it is how the shipment travels through the seller's normal machinery.
- Partial picks are normal. A line picked in part leaves the remainder open for a later shipment; nothing is lost.
- Prices are the seller's current shop prices, shown to the buyer in the buyer's own currency. If that ever changes, it will be announced — don't negotiate line by line.

<aside class="wayfinder"><strong>Permissions you need</strong>
<ul>
<li><b>See the shopping and shipping lists:</b> procurement <i>view</i> access in your organisation.</li>
<li><b>Add lines, cherry-pick, send to warehouse:</b> procurement <i>edit</i> access in the organisation doing the action (the buyer's for the list, the seller's for picking and shipping).</li>
<li><b>Receive and book in the arrived goods:</b> warehouse stock access in the buyer's warehouse, same as any supplier delivery.</li>
<li>Missing any of these? Ask your admin to grant the role in <b>Sysadmin → Users</b> — permissions are per organisation, so having them in one org does not carry over to its partner.</li>
</ul>
</aside>
