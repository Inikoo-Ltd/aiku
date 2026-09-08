---
title: Stock valuation is a setting, not a column
summary: Weighted average cost and FIFO computed in one pass over the movement history, a group-level switch that decides which one is "official", and why LIFO is deliberately missing.
date: 2026-08-13
tags: inventory, accounting, postgres
---

<aside class="tldr"><strong>TL;DR</strong>aiku computes weighted average cost (WAC) and FIFO in one pass over the stock movement history, storing both per movement with a per-organisation cutoff date. LIFO is deliberately not built because IFRS prohibits it. Which method is "official" is one group-level enum setting with an audited switch, not a column per SKU — and a missing-purchase-cost bug that had gone unnoticed for five months got two repair commands, leaving about 1.5% of purchases still costless on purpose.</aside>

Ask three accountants what your stock is worth and you get three numbers: last purchase price, weighted average cost, first‑in‑first‑out. All three are defensible. Only one of them is *the* figure on the balance sheet, and which one depends on jurisdiction and on what your auditors signed off last year.

We used to store one number per SKU, computed from the last purchase price, and call it the value. That is not allowed in the UK and it moves every time a supplier changes a price. So we rebuilt it.

## Replay the history once, get every method

Every stock movement — purchase in, sale out, adjustment, transfer — is a row in a history table. Valuation is a fold over those rows. We wrote one replay that carries the state for both methods and emits both per movement:

- **WAC**: running quantity and running value; `wac = value / quantity`; resets to purchase cost when on‑hand reaches zero or below.
- **FIFO**: a queue of cost layers; outflows consume from the oldest; non‑purchase inflows are layered at the current FIFO cost; layers reset when on‑hand goes negative.

The result lands in the history as `wac_per_sku`, `fifo_per_sku` and the value columns in organisation and group currency, with `NULL` meaning *before the cutoff* so it can never be confused with zero.

There is a cutoff date per organisation. Before it, the opening balance is valued at last purchase price; after it, the replay is authoritative. Backfill runs per SKU as queue jobs ordered by peak value, so the numbers that matter most arrive first.

## LIFO is missing on purpose

IFRS prohibits it. Building a valuation nobody may legally report is how spreadsheets fill up with wrong numbers. Leaving it out was a line in the design, not an oversight.

## "Official" is one enum

```php
enum OrgStockValuationMethodEnum: string
{
    case WAC  = 'wac';
    case FIFO = 'fifo';

    public static function official(): self
    {
        // group setting → config fallback → FIFO
    }
}
```

Everything that needs a value — margins, cost of goods sold, product costing, dashboards, four stock‑history tables, three spreadsheet exports, the tooltip on the stock screen — asks `official()`. The stored last‑purchase‑price fields still exist for people who want to see them, but they are never the accounting figure, and if somebody stores LPP as the official method the enum silently answers FIFO.

The switch lives in the group settings as two radio cards, with a confirmation that tells you, in plain words, to consult every accountant in the group before pressing it. The change is audited. After switching you run three rehydrate commands per organisation; the UI tells you which.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>The switch is <code>OrgStockValuationMethodEnum</code> in <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Enums/Inventory/OrgStock/OrgStockValuationMethodEnum.php">app/Enums/Inventory/OrgStock/OrgStockValuationMethodEnum.php</a>, with cases <code>WAC</code> and <code>FIFO</code> and an <code>official()</code> resolver.</li>
<li>Everything reading a value calls <code>official()</code> rather than storing the method locally, so a stray "last purchase price" setting can never silently become the accounting figure.</li>
</ul></aside>

## The bug we found along the way

The purchase cost on movements had been filled by a one‑off import and then by nothing. Every purchase for five months had a null cost, and WAC carried forward a stale figure without complaint. Two repair commands later — one that pulls landed costs from the old system, one that derives cost from the purchase amount where there is nothing better — about 1.5% of purchases remain costless. That is fine: WAC carries forward, and the next purchase corrects it.

## What we would tell our past selves

Value is a *function of history plus a policy*, not a fact about a SKU. Store the history, compute all the policies you are allowed to report, and make "which one counts" a setting someone accountable can change.

<aside class="tldr bottom"><strong>In one paragraph</strong>Stock value is a function of history plus a policy, not a fact about a SKU — store the movement history, compute every method you are legally allowed to report, and make "which one counts" a setting someone accountable can change.</aside>
