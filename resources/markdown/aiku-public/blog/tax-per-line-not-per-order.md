---
title: Tax per line, not per order
summary: For years every line we sold carried the same VAT rate, so one rate per order was simply true. Then the range grew to include tea — zero‑rated in one market, reduced in another — and "which rate" became a question per line. The answer was not "read the product's tax code": it was presets instead of codes for staff, tax versioned like price on the historic line, frozen once sold, and a migration that re‑rated the open baskets to the penny.
date: 2026-08-02
tags: tax, accounting, ordering, postgres
---

For most of aiku's life the question "which VAT rate does this line pay" had one answer per order, and that answer was right: giftware, candles, aromatherapy, incense — everything in the range sat at the standard rate of whichever country the order fell under. One tax category on the order, applied to every line, was not a simplification; it was the truth of the catalogue.

Then the range changed. We started selling tea, and then other food and drink, and a box of herbal tea next to a candle on a UK order is zero‑rated next to twenty per cent. In Spain the same tea is at a reduced rate and the candle is not. A per‑order rate stopped being true the day the first tea shipped, and the per‑product tax data that had been sitting in the catalogue since the migration — stored, never needed — suddenly was. So we rebuilt how tax attaches to a line.

## Tax belongs to the line

The first change is the obvious one: every order line resolves its own tax category, and the order's totals are the sum of the lines by rate. A basket shows "£14,527.96 at 20% + £62.01 at 0%" and the invoice says the same. That is one afternoon's work. The other decisions took longer.

## Staff never see tax codes

The temptation is to put a tax‑category dropdown on the product. It has forty entries per country, it is different per country, and the person editing a product is a buyer, not an accountant. So at the master‑product level there are three **presets**, shown as cards: *Standard*, *Food*, *Dried flowers* (one market only) — plus a read‑only *Custom* for the handful of products whose map matches none. A preset is a named list of countries and the reduced rate that applies in each. The buyer picks a card; the system expands it to the per‑country map that the money path reads.

The preset name is stored for bulk edits and *derived again* on every write of the map, never trusted on its own. The expanded map is the only thing the price calculation ever reads.

## Tax is versioned like price

Prices in aiku are versioned: a line points at the *historic* version of the product it was sold at, so a price change tomorrow does not rewrite today's invoice. Tax now rides the same rail. The historic product version carries the tax map it had when it was created; a line resolves its tax from its historic first and only falls back to the live master for rows older than the migration.

The consequence is exactly what accountants want: change a product's preset and a new historic version is minted; open baskets move to it and reprice; **sold lines are frozen**. A tax change is a fact about the future, not a correction of the past.

## The repair

Going live was a data problem as much as a code one. The legacy system held per‑product tax data for hundreds of thousands of products, and its "empty" marker differed between the live copy and our local mirrors — a filter that looked right locally chewed through 409,000 rows of filler in production for hours before we noticed. Once fixed, the seeder mapped 166 products to *Food* and 10 to *Dried flowers*, zero to *Custom*, and re‑rated the open baskets: 305 UK tea lines to 0%, Spanish lines to their 10% and 11.4% reduced rates, with the rehearsed basket matching production to the penny.

Historic invoices are **not** recalculated; anything about the past is a conversation with the accountants, not a script. We have the number; the decision is theirs.

## A note on credit notes

A VAT‑only credit note must return exactly the VAT the invoice charged — per line, per rate — not a recomputation at today's rates. That was its own small fix and its own test.

## What we learned

Tax is a property of the line, versioned like price, chosen by staff as a named intent rather than a code, and frozen once sold. And a model that was true for years can stop being true the day the catalogue grows — the range changes faster than the schema, so the schema should be ready for the range.
