---
title: Tax per line, not per order
summary: For years VAT was calculated once for the whole order, so zero‑rated tea on a UK invoice was charged at 20%. The fix was not "read the product's tax code" — it was presets instead of codes for staff, tax versioned like price on the historic line, frozen once sold, and a repair that re‑rated three hundred open baskets to the penny.
date: 2026-08-02
tags: tax, accounting, ordering, postgres
---

A customer in the UK buys a box of herbal tea and a candle. Tea is zero‑rated; the candle is 20%. For a long time aiku charged 20% on both — because the order carried one tax category, applied to every line, and the per‑product tax data that had been imported from the legacy system was stored and never read.

Nobody noticed for a surprising time, because the invoices *looked* right: one rate, one total, the arithmetic correct. It took a customer's accountant to notice the rate was wrong for the tea. Two tickets later, we rebuilt how tax attaches to a line.

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

Historic invoices are **not** recalculated. The amount of VAT over‑charged in the past is a conversation with the accountants, not a script. We have the number; the decision is theirs.

## A note on credit notes

A VAT‑only credit note must return exactly the VAT the invoice charged — per line, per rate — not a recomputation at today's rates. That was its own small fix and its own test.

## What we learned

Tax is a property of the line, versioned like price, chosen by staff as a named intent rather than a code, and frozen once sold. And "the invoice looks right" is not evidence; only an accountant with a calculator is.
