---
title: Buying from a partner
summary: The buyer's guide - keep the shopping list filled by hand, from the partner's catalogue, or with an auto-fill budget, then receive the goods when they arrive.
date: 2026-08-31
tags: procurement, intercompany, shopping-list
series: Ordering from partners
order: 2
---

<aside class="tldr">
For the people who <em>place</em> partner orders. You keep one open list of what your organisation needs; the partner ships against it at their own pace. Add lines by hand, from their catalogue, or let auto-fill propose a replenishment inside a budget. New to the flow? Start with the <a href="/docs/ordering-from-a-partner-organisation">overview</a>.
</aside>

## The shopping list

In **Procurement → Partners**, open your partner and go to its **Shopping list**.

- **Add stocks** opens the partner's stock list with their availability, how each item is packed, your own current stock, and how much you have used over the last four quarters. Quantities are in the seller's shipping units (SKOs).
- Each line shows *have + buy → result* and the value at the partner's current price — in **your organisation's currency** — so you can see what the open list would cost.
- Open lines are fully yours: change the quantity, the **priority** (low → urgent) and the **needed-by date** right in the table, or delete the line. Once the partner picks a line it locks, and its state tells you where it is.

## Browsing the partner's catalogue

Next to the shopping list there is a **Browse** tab: the partner's whole catalogue as a shop, with live stock and prices. Move through it by **Departments** or **Collections**, drill down to families, or just type in the search box. Every product card shows the current price, what the partner has available, and an **Add** button that puts it straight on your shopping list with the quantity you choose.

While you browse, your shopping list rides along as a receipt pinned to the right — every line grouped by family, with the running total — so you always know where the order stands. **Go to Shopping list** takes you back to the full editable list.

<figure><img src="/art/docs/draw-partner-browse.svg" alt="Watercolor sketch of the partner catalogue browser: a search box, Departments and Collections tabs, product cards with plus buttons, and the shopping list receipt pinned on the right with its running total" width="1200" height="750" loading="lazy"><figcaption>The partner's shop, with your list riding along.</figcaption></figure>

## Auto-fill: a budget and, if you like, an instruction

Auto-fill exists so replenishment doesn't depend on someone remembering every item. You give it one number — a **budget in your own organisation's currency** — and it builds a proposal that fits inside it:

- It looks at every item the partner can supply that you actually use, ranks them by **stock cover** (what you hold divided by what you use in a quarter), and tops up the ones that will run out first, aiming at roughly a quarter's worth of each.
- Every proposed line shows its **reason** ("you use ~96/quarter and hold 0"), the quantity and the cost, so you can see why it is there.
- The **instruction box** is optional and takes plain language: *"prioritise essential oils, skip anything we hold over 8 weeks of"*, *"focus on candles, nothing seasonal"*. An AI reads your instruction together with the same usage data and reshapes the proposal accordingly — but its output is checked against reality before you see it: quantities are capped at what the partner actually has, and the total is forced back inside your budget. If the instruction can't be followed, you get the standard proposal instead.
- **Nothing is added by itself.** The proposal is a set of ticked lines you can untick, re-quantify or regenerate with a different budget or instruction; only **Add items to shopping list** commits anything.

A good habit: run Auto-fill with your usual budget once a replenishment cycle, read the reasons, untick what you disagree with, and add the rest.

## When the goods are on their way

Once the partner [sends a shipment to their warehouse](/docs/fulfilling-partner-orders), an incoming **stock delivery** appears under your partner's **Stock deliveries**. Leave it alone while it says confirmed or dispatched — it mirrors the seller's warehouse and updates itself. When the boxes physically arrive: **receive**, check, and place into locations exactly as you would for any supplier delivery. Anything short or damaged is dealt with after receiving, against the linked invoice — see [the overview](/docs/ordering-from-a-partner-organisation) for how money works.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Add to the list:</b> your organisation → <b>Procurement → Partners</b> → open the partner → <b>Shopping list</b> → <b>Add stocks</b>, or <b>Browse</b> and use the <b>Add</b> buttons, or <b>Auto-fill</b> for a proposal.</li>
<li><b>Adjust open lines:</b> edit quantity, priority and needed-by directly in the shopping list table.</li>
<li><b>Watch and receive the shipment:</b> same partner page → <b>Stock deliveries</b> → when the goods arrive, <b>Receive</b> → check → place into locations.</li>
</ul>
</aside>
