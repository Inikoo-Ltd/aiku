---
title: Reading the partner shopping dashboard
summary: The screen that shows what to buy from a partner and how much room you have to buy it — three capacity cards, a stock-cover donut with eight buckets, and the order pipeline.
date: 2026-09-02
tags: procurement, intercompany, shopping-list, stock
category: procurement
help_routes: grp.org.procurement.org_partners.show.shopping
series: Ordering from partners
order: 2
---

<aside class="tldr">
This dashboard is where every buying session starts. The top row shows how much room you have — money and warehouse space. The middle shows which of the partner's products need ordering, worst first. The bottom shows what is already on its way. You don't have to remember anything; the screen tells you what needs attention. Placing the order itself is covered in <a href="/docs/buying-from-a-partner">Buying from a partner</a>.
</aside>

Open it at **Procurement → Partners → {partner} → Shopping**. Use it instead of opening the shopping list and trying to remember what was missing.

## The three cards at the top: how much room you have

These cards are limits, not decoration. They exist because a shopping list that anyone can dump anything into stops meaning anything — a partner who gets a thousand lines can't tell which two are urgent.

- **Order budget used.** The value of your open list compared to what this partner actually delivers to you in one order cycle, shown in your own organisation's currency — every money figure on these screens is converted for you, so you never have to think in the partner's currency. If there's enough delivery history, the budget is measured from real deliveries; if not, it's one order cycle of what you actually sell of their products. Nobody types this number in — not you, not your manager. When the bar is full the card says **at capacity**.
- **Warehouse space.** How many locations are free out of the total, with a bar split into what's *in use*, what's *inbound* on open purchase orders and deliveries, and what *this shopping list* would take up. Below that, the partner's fair share: how many of the free slots their brand-new products are allowed to use. Locations are counted as slots — we have no volume data, so we don't pretend to measure cubic metres.
- **Lead time.** Titled with the partner's name, this card shows their measured **order → booked in** lead time, how many deliveries it was measured from (or a note that it's still an estimate), how many purchase orders they're late on and by how much, and how big their catalogue is.

## Stock cover: the donut and the eight buckets

This section covers the partner's whole catalogue, split into eight buckets by how long your own stock will last. The risky buckets are sized by the measured lead time, not by calendar weeks — that's the whole point.

It opens with a **donut chart**: every product in the catalogue, one slice per bucket, with the total in the middle. Hover a slice to see the count and percentage; click a slice — or a row in the legend next to it — to browse that bucket in the partner's catalogue. One glance tells you whether today is a quiet top-up or a fire drill: lots of red means trouble, mostly green means you're fine.

Below the chart the buckets sit in two groups. **Needs ordering** holds the five that want your attention:

- **Out of stock** — nothing left on the shelf.
- **Doomed** — you still have stock, but it will run out before a delivery could arrive, even if you ordered right now.
- **Critical / Danger / Watch** — will run out within two, three or four lead times.

**Not for ordering** holds the other three:

- **Covered** — fine for now.
- **Dead stock** — nothing selling, money sitting on a shelf; the row shows what it's worth.
- **We never stocked** — the partner sells it, but you've never carried it.

One kind of item never shows up here at all: SKOs you've marked **On Demand** in your own inventory. Their stock isn't tracked, so "out of stock" would mean nothing — the dashboard, the bucket tables and Auto-fill all skip them.

Each tile answers one question: **how many still need me?** The "*N* need action" count ignores anything already on the list or already on the way, so it shrinks as you work through it. Under it, the same count broken down by **rank** — A products first, D and Z faded at the end. Two out-of-stock A products matter more than five hundred D products, so that's the order to work in.

Three things you can do from a tile:

- **Click the number** to open the bucket as a table: every item, ranked, with their stock, your stock, when you run out, and a quantity box that writes straight to the shopping list.
- **Click the bucket's name or a rank letter** to browse those products in the partner's catalogue.
- **Fill** to open Auto-fill already scoped to that bucket and already generated — you just adjust and confirm. A bit more work than a magic button, and much more control. The counts on the tile — *N on the way · N on list* — show how much of the bucket you've already handled.

On **Covered** and **Dead stock**, a red warning appears instead when something from that bucket is sitting on your shopping list: that's stock you don't need. **remove** clears those lines in one click.

## The order pipeline

The bottom strip follows everything from need to shelf: **on shopping list → being prepared → ready to ship → in transit → arrived, booking in**. Each column shows its deliveries and how many items are in them; each card opens the delivery, read-only — the seller's warehouse owns it until the goods reach you.

Cards age visibly. Past three times the lead time they turn amber; past ten times, red. An old card is a question to ask the partner, not a number to stare at. Anything genuinely late also shows up in the **Late from this partner** list underneath, worst delay first, with "no delivery date given" flagged.

## Why the screen sometimes says no

Adding to the list can be refused. That's on purpose, and there are only three reasons:

- **At the budget cap** — remove or deprioritise something first. A real emergency always fits: **A-rank and out-of-stock items are exempt**, so an emergency never waits behind a cap.
- **The warehouse floor** — with under 5% of locations free, no *new* product from anyone gets added. Items you already stock refill their own slots and pass freely.
- **This partner's fair share** — one partner can claim about a fifth of the free slots with never-stocked products. The other suppliers need room too.

The same rule applies everywhere you add — by hand, in bulk, or from Auto-fill — so a proposal never contains lines you can't commit.

## Measured, or honestly labelled a guess

Two numbers drive most of this screen: the lead time and the budget. The rule is the same for both. **If we have the history, the number is measured and can't be edited.** If we don't, the card says so, and the guess can be edited — but in settings, never directly on the dashboard: a per-product **estimated delivery time** on the supplier product or on the SKO's own settings. Once enough real deliveries exist, the measurement takes over and the estimate field disappears. Nobody gets to overwrite what actually happened.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>The dashboard:</b> your organisation → <b>Procurement → Partners</b> → open the partner → <b>Shopping</b>.</li>
<li><b>Jump from the donut:</b> click a slice, or a row in the legend, to browse that bucket in the catalogue.</li>
<li><b>Work a bucket:</b> click the tile's number for the item table, a rank letter to browse those products, or <b>Fill</b> for a scoped Auto-fill proposal.</li>
<li><b>Clean the list:</b> <b>remove</b> on the Covered or Dead stock tile.</li>
<li><b>Fix a lead-time estimate:</b> the SKO's settings, or the supplier product's settings — only while it still says <i>estimate</i>.</li>
</ul>
</aside>
