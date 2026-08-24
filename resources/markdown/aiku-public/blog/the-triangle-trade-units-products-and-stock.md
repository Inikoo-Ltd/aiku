---
title: The triangle — trade units, products, and stock
summary: The same candle is one thing to the buyer, a box of twelve to the warehouse, and a litre of base to the factory. aiku models that with three corners — the trade unit (the physical thing), the stock (that thing, in this warehouse), the product (that thing, as sold in this shop) — and a number on every edge. Why a fraction is sometimes right and sometimes a bug, and why changing a number on an edge while an order is in flight is the most dangerous edit in the system.
date: 2026-07-28
tags: architecture, catalogue, inventory, goods, postgres
---

<aside class="tldr"><strong>TL;DR</strong>aiku models a product as a triangle — trade unit (the physical thing), org stock (that thing in a warehouse) and product (that thing as sold in a shop) — connected by pivots that each carry a quantity. A fraction on that quantity is sometimes a migration bug (a 1/12 fraction copied in the wrong direction) and sometimes the honest truth (aromatics sold as fractions of a litre). Changing a product's composition while an order is in flight is the most dangerous edit: it must mint a new historic version so submitted orders stay frozen at what was actually ordered.</aside>

Ask three people in a trading company what "a lavender candle" is and you get three different answers. To the buyer it is a SKU from a supplier, in cartons of twelve. To the warehouse it is a thing on a shelf in location B‑11‑01, counted in units. To the shop it is a product at £4.50, or a six‑pack at £24, or — on a trade price list — a carton. To the factory, if we make it, it is 180 grams of wax and a wick.

Most systems pick one of those and make the others suffer. aiku models all of them, and the model is a triangle.

## Three corners

**Trade unit** — the physical thing as it exists in the world: a barcode, a weight, dimensions, ingredients, the marketing description, the images. It is group‑level and knows nothing about price or warehouses. A "6‑pack" is a different trade unit from the single, because it has a different barcode and a different weight. Trade units have families, brands, tags, a status (in process, active, discontinuing, discontinued).

**Org stock** — that trade unit as it exists in *this organisation's* warehouses: quantity on hand by location, cost, valuation, movements. One trade unit, many org stocks (one per organisation that holds it). This is the corner the warehouse and the accountants live in.

**Product** — that thing as it is *sold in this shop*: a code, a name in the shop's language, a price in the shop's currency, tax, availability on the website. One trade unit, many products — the single in shop A, the single in shop B, the six‑pack in shop A. Products hang off a **master product** at group level, which is where the catalogue team defines the thing once and pushes it down.

## A number on every edge

The corners are connected by pivots, and every pivot carries a **quantity**:

- *product ↔ trade unit*: this product is **n** of that trade unit. The single is 1; the six‑pack product is 6 × the single trade unit (or 1 × the six‑pack trade unit, if it has its own barcode — both shapes exist and the model allows both).
- *product ↔ org stock*: selling one of this product consumes **n** of that stock, with an explicit dividend/divisor for the cases where the ratio is not an integer.
- *trade unit ↔ master product, trade unit ↔ ingredients*: the same idea, up and down.

The product also carries a scalar `units` — a denormalised "how many physical units is one of these" — which must always agree with its pivot. That agreement is an invariant, and it is the one that breaks.

<figure><img src="/art/readme/draw-note-triangle.svg" alt="Sketch of the trade unit, product, and org stock triangle with quantities on each edge" width="1200" height="750" loading="lazy"><figcaption>The number on the edge is the truth.</figcaption></figure>

## When a fraction is a bug and when it is the truth

When we migrated from the previous system, a few thousand master products arrived with `units` like 0.083 and 0.071. The old system had stored a single piece of a 12‑pack as *1/12 of the outer*, and the migration faithfully copied the fraction into a model that expected "a product is n trade units", not "a product is 1/n of a trade unit". The signature was easy to spot once you knew it: 2019 timestamps, no audit user, products saying 1 while their masters said 0.083.

So we wrote the repair: for each master, take the master's units, the majority of its live children, and its own pivot; when all three agree and one product dissents, the product is wrong; when they do not agree, flag it for a human. The command runs to a fixed point — fix, report, fix — because correcting one child shifts the majority for the next. Hundreds of products, three shops, done in a minute, verified by price consistency.

Then we came to the aromatics business and found 1,500 fractional masters — and **they were correct.** An aromatics company sells 100 ml of a litre and 500 g of a kilo; the base trade unit is the litre, and 0.1 is the honest quantity. Internally consistent, no 1/12 signature, nothing to repair. The lesson that went into the checklist: *a fraction is not a bug; a fraction that disagrees with its own pivot is.*

## The most dangerous edit in the system

Everything the warehouse does with an order is expressed in stock units via those edges. So if someone changes a product's composition — say, from "1 × trade unit" to "4 × trade unit" — while an order for that product is already in the warehouse, the delivery note's required quantity follows the edge and silently becomes four times what the customer ordered, while the price stays at the old per‑unit figure. We have had exactly that happen: an order submitted on a Tuesday, the units changed on Wednesday morning, dispatched Wednesday afternoon with four candles billed where one was ordered.

The rules that came out of it:

- A composition change is a **new historic version** of the product, like a price change. Open baskets move to it and reprice; **submitted orders do not**.
- The delivery note's required quantity is frozen from the order's historic line, not recomputed from the live product.
- Pack‑size display on the floor follows one contract everywhere: *the value stays in packs; only the fractional part is shown as loose units over the pack size.* A rewrite that multiplied every quantity by the pack size — which looked right in one screenshot — broke every order screen for a day and was reverted.
- When a whole group of shops must match their master's pack size, the repair writes through the real sync action so weights, stock links and flags cascade, and it requires a *strict* majority before it calls a product wrong.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>The units-integrity repair command is <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Maintenance/Masters/RepairMasterProductUnitsIntegrity.php">app/Actions/Maintenance/Masters/RepairMasterProductUnitsIntegrity.php</a>.</li>
<li>The group-wide sync that cascades weights, stock links and flags when a master's pack size changes is <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Maintenance/Masters/SyncMasterChildTradeUnitCompositionToMaster.php">app/Actions/Maintenance/Masters/SyncMasterChildTradeUnitCompositionToMaster.php</a>.</li>
</ul></aside>

## Why keep the triangle at all

Because collapsing it is how you lose something. Collapse trade unit into product and you cannot share a barcode, a weight, an ingredients list or an image across thirty shops. Collapse stock into product and you cannot store one thing once and sell it as a single, a six‑pack and a carton. Collapse product into stock and you cannot price the same thing differently in Spain and in the UK. Three corners, a number on every edge, and the discipline that the number on the edge is the only truth — that is what lets a candle be one thing to the buyer, twelve to the warehouse and a litre to the factory, and still be one row in the stock count.

<aside class="tldr bottom"><strong>In one paragraph</strong>Three corners — trade unit, stock, product — with a number on every edge, and the discipline that the number on the edge is the only truth, is what lets one candle be a single to the buyer, twelve to the warehouse and a litre to the factory, and still be one row in the stock count.</aside>
