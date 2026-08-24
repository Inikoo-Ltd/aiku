---
title: An order that someone else keeps editing
summary: Selling through a wholesale marketplace means the order is not yours: the buyer edits it after you have picked it, the marketplace sets the discount and the commission, the items can change after dispatch, and the only truth is their payload. The shops we run on such a marketplace taught us more about ownership than any other integration — six rules, each with the afternoon behind it.
date: 2026-08-20
tags: marketplaces, ordering, warehouse, integrations, data
---

<aside class="tldr"><strong>TL;DR</strong>Selling through a wholesale marketplace means the marketplace, not the shop, owns the order: its payload sets prices, discount and commission; buyers can edit orders mid-pick; lines can appear after dispatch; and returns and refunds happen on the marketplace's side, not ours. Six hard-won rules cover this: never let the discount engine touch external-shop numbers, accept edits in any state short of dispatched, give homeless post-dispatch lines an explicit destination, never bulk-resync terminal orders, filter every "first()" lookup by type, and skip the return button entirely for these shops.</aside>

Most of our marketplace integrations are *outbound*: we list, they order, we ship. The wholesale marketplace is different. Retailers buy from our brand's storefront *on their platform*; the marketplace collects the money, applies its own promotions and commission, pays us later, and — the part that reshaped our code — lets the buyer keep editing the order while it moves through our warehouse. Each of our brands on it is a separate **external** shop in aiku, and this note is the six rules those shops forced on us.

## 1. Their payload is the money

The marketplace tells us, per order, the item prices the buyer paid, the brand discount it applied, the tax, the commission. Our own pricing and [discount engine](/blog/ninety-one-thousand-offers-one-calculator) must never touch any of it. We made that structural: for external shops the discount calculator returns early, and the fields come from their payload — the brand discount is their number, carried onto our invoice unchanged. The guard lives in the engine, not at the call site, because nine code paths reach it and one forgotten caller is a drift. Invoices from these shops agree with the marketplace's statement to the penny because they are *made from* it.

## 2. Accept the edit, whatever state you are in

A buyer can change quantities, add a line or remove one after we have picked and packed. Our first instinct was to refuse once the note was past handling. Wrong: the marketplace is the buyer's system of record, and a refusal just means the two systems disagree forever. Now an edit is accepted in every state short of dispatched: the delivery note walks back — unpack, undo the pick, back to handling — in one locked transaction, shared with the ordinary "customer changed quantity" path, and the picker sees the changed line with a reason.

## 3. A line added after dispatch is real, and it is not yours to ship

Sometimes the buyer adds an item *after* we dispatched. The marketplace creates the line; we receive it; there is nothing to pick because the box has gone. Those lines used to be born in a half‑state with no home. Now they are recognised for what they are — a genuine marketplace line with its own identifier, added post‑dispatch — and routed to customer service rather than left to inflate an order total. The rule: *a line you cannot act on must still be explained, not hidden.*

## 4. Never resync history in bulk

A "resync this order from the marketplace" command is indispensable — it is how a stale order is brought back into line. A version of it that accepted *no* order and looped every order ever, with no state filter, is how a month's worth of dispatched lines and billed invoices were quietly re‑derived against today's product data. We learned that on a Monday. The command now refuses to touch dispatched, finalised or cancelled orders in bulk; per‑order remains possible, on purpose, with a person at the keyboard. The general rule went on the wall: **a bulk writer must exclude terminal states by default**.

## 5. "First related row" is a bug waiting for a refund

Two lookups in the sync took *the first invoice* of an order and *the first invoice transaction* of a line. On an order that has been partly refunded there are two of each — the invoice and its credit note, the positive row and the negative one — and "first" is whichever the database felt like. Both lookups are now filtered by type; both have a test that builds the refunded case first. The rule: on any model that can have a mirror (invoice/credit, payment/refund), *first()* without a type filter is a defect.

## 6. Returns and refunds happen where the order lives

A buyer's return is raised on the marketplace, and the money moves there. So on a dispatched order from an external shop there is deliberately **no return button** in our app; goods that come back are received into stock — [the box is ours](/blog/eight-marketplaces-one-warehouse-queue) — and the refund is theirs. The rule was added after customer service raised a return on our side in good faith and the two ledgers stopped agreeing.

## What it felt like, honestly

For a stretch this summer most of the hardest tickets had the marketplace's name on them, and each fix surfaced the next: the discount, then the edit‑in‑flight, then the post‑dispatch lines, then the bulk resync, then the refund mirror. It was a nightmare in the specific sense that every assumption we held about *our* orders turned out to be conditional on us owning them. The payoff is that the six rules above are now tests, guards and one‑line decisions in the code, and the shops on that marketplace reconcile to its statements without anyone touching a spreadsheet.

If you are about to sell through a marketplace that owns the order: decide *in the engine* whose numbers win, accept edits in every state you can, make a home for lines you cannot act on, never bulk‑write terminal history, filter every "first", and let returns happen where the money is.

<aside class="tldr bottom"><strong>In one paragraph</strong>Every assumption about owning an order turns out to be conditional on actually owning it, and a marketplace integration has to be built around that fact everywhere at once.</aside>
