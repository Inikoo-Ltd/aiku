---
title: Seventeen million stock movements
summary: Stock in aiku is never a number you set; it is the sum of movements you record — purchase, pick, return, transfer, production, found, write‑off, audit. How 88,000 stock items across 45,000 locations stay honest: a location‑stock row per bay, a picking location per item, audits as movements with a delta and a reason, a lock that stops a low count being audited mid‑pick, and the "error" naming rule that keeps a retired SKU from ever being picked again.
date: 2026-08-14
tags: inventory, warehouse, postgres, audits
---

<aside class="tldr"><strong>TL;DR</strong>Stock quantities are never written directly; they are the sum of seventeen million typed movements (purchase, pick, return, transfer, production, found, write-off, audit) across 88,000 org stocks and 45,000 locations. Audits are movements too — they record a delta against the ledger rather than overwriting a count, and a lock stops a low-stock audit racing an in-flight pick. A migration-era <code>-error</code> naming convention on retired SKUs caused live products to sometimes bind to a discontinued twin; the fix is to prefer active org stocks and treat "error" in a name as never the real one.</aside>

Ask the system how many lavender candles are in the UK warehouse and it will tell you, instantly, from a counter on the stock row. But that counter is not where the truth lives. The truth is **seventeen million stock movements** — every time a unit came in, went out, moved, was found, was written off or was counted — and the counter is their sum. This note is how that is organised, and why we will not let anyone type a stock figure directly.

*every shelf remembers what it held.*

## The shape

- A **stock** is the group‑level thing ([a trade unit](/blog/the-triangle-trade-units-products-and-stock), as stock).
- An **org stock** is that stock in one organisation: 88,000 of them. It carries the counters — on hand, allocated, available — and the valuation.
- A **location** is a bay, a shelf, a pallet space: 45,000 across the warehouses, with a code, a zone, a status (*operational* or *broken*, because shelves do break), and what it can hold.
- A **location org stock** row is *this stock, in this location*: 59,000 of them. Quantity, whether it is the item's **picking location** (the one the route sends a picker to first; it can be set by hand), and when it was last audited.

A stock item is the sum of its location rows; a location is the sum of what sits in it. Both sums are hydrated at the point of change and reconciled at night.

## Movements, typed

Every change in quantity is an **org stock movement** with a type — *purchase, cancel purchase, picked, cancel picked, return dispatch, return picked, location transfer, consumption, return consumption, production, found, write‑off, adjustment, audit* — a flow (*in, out, audit*), a quantity, a location, a cost where one is known, and a link to whatever caused it: a stock delivery item, a delivery note item, a job order, a person. The warehouse never edits a quantity; it does something, and the something records a movement.

That discipline is what makes [stock valuation](/blog/stock-valuation-is-a-setting) possible — WAC and FIFO are folds over the movement history — and what makes "why does this say 12" answerable: the movements that made it 12 are listed, newest first.

## Audits are movements too

A count is the one moment the physical world overrides the ledger, so it is modelled carefully. An **audit** of a location‑stock row records what the system said, what the person counted, the **delta**, a reason and the counter's identity, and writes a movement of type *audit* for the difference. Audits can be done one bay at a time from the tablet or in bulk for a zone. An audit does not "set" the quantity; it records that the count disagreed with the ledger by *n*, and the ledger now follows. A year later the audit deltas are a report — which zones drift, which items, by how much.

One guard we added after watching the floor: **a low‑stock audit is locked while a pick is in flight** on that row. Without it, a picker takes the last three, an auditor counts zero at the same moment, and the ledger records a phantom loss of three. The lock is short and boring and it closed a whole class of deltas.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>Movements: <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Models/Inventory/OrgStockMovement.php">app/Models/Inventory/OrgStockMovement.php</a>, written via <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Inventory/OrgStockMovement/StoreOrgStockMovement.php">app/Actions/Inventory/OrgStockMovement/StoreOrgStockMovement.php</a>.</li>
<li>Audits: <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Inventory/LocationOrgStock/AuditLocationOrgStock.php">app/Actions/Inventory/LocationOrgStock/AuditLocationOrgStock.php</a> and the bulk variant <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Inventory/LocationOrgStock/BulkAuditLocationOrgStock.php">app/Actions/Inventory/LocationOrgStock/BulkAuditLocationOrgStock.php</a>.</li>
</ul></aside>

## Lost, found, and the `-error` rule

Things go missing and things turn up. *Found* is a movement with no purchase behind it; a write‑off is the reverse. Both need a reason.

A stranger rule came from a migration. When a SKU was retired and re‑created in the old system, the retired one kept its code with `-error` appended. Years later a link‑building routine took "the first org stock for this stock in this organisation" — with no state filter — and sometimes bound a live product to the discontinued `-error` twin, so pickers were sent to the wrong shelf in every shop that followed the master. The fix was to prefer active org stocks and to repair the links; the rule that went into the checklist was blunter: **anything with "error" in its name is never the real one**, and you check the *stock's* name, not only the org stock's, because the impostor can be on the correct stock and the genuine one on the `-error` stock.

## Moving stock between bays

A location transfer is a pair of movements, out of one row and into another, in a transaction. Mass moves (empty this bay into that one) and partial moves (take forty from here to there) are actions with their own authorisation, because moving stock is how stock gets lost; both leave the picking‑location flag where it was unless told otherwise.

## Histories and the dashboard

Every night each org stock writes a **history** row: quantities, value by each valuation method, dormant and never‑sold flags. Organisation and group roll‑ups of those rows are what the stock dashboard reads — stock value, stored SKUs, locations, out of stock, dormant for a year, no sales for a year — instantly, because the folding already happened.

## What we would tell our past selves

Never let a quantity be written; only movements. Type the movements generously. Treat an audit as a movement with a delta and a reason, not as an overwrite. Lock the count against the pick. And if your migration leaves twins with a warning in their name, believe the warning.

<aside class="tldr bottom"><strong>In one paragraph</strong>Never let a quantity be written directly — type every movement, treat an audit as a movement with a delta and a reason, lock the count against the pick, and believe a migration's own warning when it names a twin "error".</aside>
