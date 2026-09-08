---
title: Reading the partner shopping dashboard
summary: The screen that tells you what to buy from a partner and how much room you have to buy it - three restraint cards, eight stock-at-risk buckets and the order pipeline.
date: 2026-08-31
tags: procurement, intercompany, shopping-list, stock
category: procurement
help_routes: grp.org.procurement.org_partners.show.shopping
series: Ordering from partners
order: 2
---

<aside class="tldr">
The dashboard is the start of every buying session. The top row says how much room you have — money and warehouse space. The middle says which of the partner's products are about to hurt you, worst first. The bottom says what is already moving. You never have to remember anything; you answer what the screen asks. Placing the order itself is covered in <a href="/docs/buying-from-a-partner">Buying from a partner</a>.
</aside>

Open it at **Procurement → Partners → {partner} → Shopping**. It replaces the old habit of opening the shopping list and trying to remember what was missing.

## The three cards at the top: what room you have

These are restraints, not decorations. They exist because a shopping list that anybody can dump anything into stops being a signal — a partner who receives a thousand lines cannot tell which two are emergencies.

- **Order budget used.** The value of your open list against what this partner *actually delivers to you* in one order cycle. When there is enough delivery history it is measured from those deliveries; before that, it is one order cycle of what you genuinely sell of their products. Nobody types this number in — not you, not your manager. When the bar is full the card says **at capacity**.
- **Warehouse space.** How many locations are free out of the total, with a bar splitting it into what is *in use*, what is *inbound* on open purchase orders and deliveries, and what *this shopping list* would claim. Underneath, this partner's fair share: how many of the free slots their brand-new products may take. Locations are counted as slots — we have no volume data, so we do not pretend to measure cubic metres.
- **The partner card.** Their measured **order → booked in** lead time, how many deliveries it was measured from (or that it is still an estimate), how many purchase orders they are late on and by how much, and the size of their catalogue.

## Stock at risk: eight buckets, worst first

The block of tiles covers the partner's whole catalogue, split by how long your own stock lasts. The dangerous tiers are sized by that measured lead time, not by calendar weeks — which is the point:

- **Out of stock** — nothing on the shelf.
- **Doomed** — you still have stock, but it is mathematically gone before a delivery could land even if you ordered this second.
- **Critical / Danger / Watch** — out within two, three or four lead times.
- **Covered** — fine.
- **Dead stock** — nothing selling, money sitting on a shelf; the tile shows what it is worth.
- **We never stocked** — the partner sells it, you have never carried it.

Each tile answers one question: **how many still need me?** The "*N* need action" count ignores anything already on the list or already on the way, so it drops to nothing as you work. Under it, the same count broken down by **rank** — A products first, D and Z faded at the end. Two out-of-stock A products matter more than five hundred D shrugs, so that is the order you work in.

Three things you can do from a tile:

- **Click the number** to open the bucket as a table: every item, ranked, with their stock, your stock, when you run out, and a quantity box that writes straight to the shopping list.
- **Click the tile or a rank** to browse those products in the partner's catalogue.
- **+ fill** to open Auto-fill already scoped to that bucket and already generated — you adjust and confirm. More work than a magic button, and much more control.

On **Covered** and **Dead stock**, a red warning appears instead when something from that bucket is sitting on your shopping list: that is stock you do not need. **remove** clears those lines in one click.

## The order pipeline

The bottom strip follows everything from need to shelf: **on shopping list → being prepared → ready to ship → in transit → arrived, booking in**. Each column shows deliveries and the number of items in them; each card opens the delivery, read-only — the seller's warehouse owns it until the goods reach you.

Cards age visibly. Past three times the lead time they turn amber; past ten times, red. An old card is a question for the partner, not a number to admire. Anything genuinely late also appears in the **Late from this partner** list underneath, worst delay first, with "no delivery date given" called out.

## Why the screen says no sometimes

Adding to the list can be refused. It is deliberate, and there are only three reasons:

- **At the budget cap** — remove or deprioritise something first. A real crisis always fits: **A-rank and out-of-stock items are exempt**, so an emergency never waits behind a cap.
- **The warehouse floor** — under 5% of locations free, no *new* product from anyone gets added. Items you already stock refill their own slots and pass freely.
- **This partner's fair share** — one partner may claim about a fifth of the free slots with never-stocked products. The other suppliers need room too.

The same guard applies wherever you add — by hand, in bulk, or from Auto-fill — so a proposal never contains lines you cannot commit.

## Measured, or honestly labelled a guess

Two numbers drive most of this screen: the lead time and the budget. The rule for both is the same. **If we have the history, the number is measured and cannot be edited.** If we do not, we say so on the card, and the guess is editable — but in settings, never inline on the dashboard: a per-product **estimated delivery time** on the supplier product or on the SKO's own settings. Once enough real deliveries exist, measurement takes over and the estimate field disappears. Nobody gets to overwrite what actually happened.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>The dashboard:</b> your organisation → <b>Procurement → Partners</b> → open the partner → <b>Shopping</b>.</li>
<li><b>Work a bucket:</b> click the tile's number for the item table, or a rank letter to browse those products, or <b>+ fill</b> for a scoped Auto-fill proposal.</li>
<li><b>Clean the list:</b> <b>remove</b> on the Covered or Dead stock tile.</li>
<li><b>Fix a lead-time estimate:</b> the SKO's settings, or the supplier product's settings — only while it still says <i>estimate</i>.</li>
</ul>
</aside>
