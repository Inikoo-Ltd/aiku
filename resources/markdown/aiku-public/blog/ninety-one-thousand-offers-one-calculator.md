---
title: Ninety‑one thousand offers, one calculator
summary: Trade discounts are not "10% off with code". They are forty trigger types — quantity of a family, amount across a department, every nth order, first order, cartons, gifts, vouchers, customer‑exclusive deals — stacked, metered, re‑evaluated on every basket change, and written to every invoice line as an allowance you can audit. How aiku's discount engine works, why buy‑X‑get‑cheapest‑free counts the way it does, and the penny that float arithmetic tried to steal.
date: 2026-08-22
tags: discounts, ordering, pricing, postgres
---

<aside class="tldr"><strong>TL;DR</strong>About 91,000 offers across roughly forty trigger types feed one ~1,000-line calculator action that runs on every basket change: it loads offers, builds running meters per trigger, decides allowances per line, and writes each one to a pivot row so every discounted amount traces to a cause. Submitted orders freeze to the offers in force at submission; marketplace orders skip the engine entirely. A float rounding bug (<code>1 − 0.9</code>) was quietly stealing a penny on 10%-off lines ending in .x5 until it was fixed to round the discount amount at the point it's computed.</aside>

A consumer shop has a coupon box. A wholesaler has a *price book* — and the price book is alive. Buy six of this family and the seventh is free. Spend over a threshold in that department and the whole department is 10% off. Your third order this quarter earns a gift. A carton of twelve is priced as a carton. This customer, and only this customer, gets an extra 5% on candles because someone agreed it in 2019. All at once, on the same basket, recalculated every time a line changes.

Our numbers today: about **91,000 offers** in the database across the group's shops, organised into campaigns, and **4.6 million** order lines that carry at least one allowance from them. This note is how the engine that applies them works.

## Offers, triggers, allowances

An **offer** is three things: a **trigger** (what has to be true), an **allowance** (what you get), and a window (when, and for whom). The trigger vocabulary is wide because the business vocabulary is wide — family quantity, category amount, department amount, "for every N ordered", order interval, order number, first order, voucher, customer‑exclusive, carton, shop‑wide — roughly forty types. The allowance vocabulary is narrow on purpose: a percentage off, an amount off, a gift, discounted shipping, or a free item among those bought.

Offers live in **campaigns** so they can be scheduled, suspended, finished and reported together; an offer can be made permanent; a scheduler activates and expires them by date.

## One calculator

There is one place that turns a basket into discounted lines: a single action of about a thousand lines that runs on every basket change and on submission. It:

1. Loads the offers in force for this shop and customer — the shop's campaigns, the voucher the customer typed, the exclusive offers attached to this customer.
2. Builds **meters**: running totals per trigger signature (this family's quantity, that department's amount, the order total), so that "for every 6" and "over £100" can be answered without rescanning the basket for each offer.
3. Walks the lines, decides which allowances apply to which lines, and records the result on a pivot — *transaction has offer allowance* — one row per line per offer, with the amount, the type and whether it is a gift.
4. Writes the discounted amounts back to the lines and the totals to the order.

Every discount is therefore a **row with a cause**. An invoice line's net amount can be traced to the exact offer that produced it, which is the difference between "we gave 10% somewhere" and a report that says which campaign cost what, which is what the [marketing dashboard](/blog/marketing-attribution-that-adds-up) reads.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>The calculator is <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Ordering/Order/CalculateOrderDiscounts.php">app/Actions/Ordering/Order/CalculateOrderDiscounts.php</a>.</li>
<li>The "row with a cause" pivots: <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Models/Discounts/TransactionHasOfferAllowance.php">app/Models/Discounts/TransactionHasOfferAllowance.php</a> and <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Models/Discounts/InvoiceTransactionHasOfferAllowance.php">app/Models/Discounts/InvoiceTransactionHasOfferAllowance.php</a>.</li>
</ul></aside>

## Submitted means frozen

Baskets are recalculated on every change. Submitted orders are not — the discounts are regenerated only in the narrow cases where an order is legitimately edited after submission, and then from the offers that were in force *at submission*. A campaign ending on Monday does not change an order placed on Sunday.

Marketplace orders bypass the engine entirely: for shops typed *external*, the calculator returns early, because [the marketplace owns the money](/blog/eight-marketplaces-one-warehouse-queue).

## Buy X, get the cheapest free — counted our way

"Buy two, get the cheapest free" can be read two ways: *put two in the basket and the cheaper is free* (up to 50% off), or *put three in and the cheapest of three is free* (up to 33%). We count the first way — `min(floor(total ÷ X) × free, total)` — and it is written down as a decision, with the other reading recorded as considered, because a year from now someone will read the offer name and assume the other one. An offer engine's most important documentation is the list of readings it did *not* choose.

## The penny

A percentage discount is stored as a factor: 10% off is 0.9. In a double, `1 − 0.9` is `0.09999999999999998`. A gross price ending in 5 — 61.95 — times that factor lands a hair below the half‑penny and rounds the wrong way. Only 10% does it (other factors cannot put a two‑decimal price on a half penny), and only on prices ending in 5, which is why it was a penny on eight lines of one order and seventeen of another before anyone saw the pattern.

The fix is to compute the discount *amount* from the gross and round it at that site, then subtract — never `gross × (1 − factor)`. Every money computation in the engine now rounds where it is made, and a regression test holds the 61.95 case.

## What the sales team gets

A screen per offer with its trigger, its allowance, its window and its performance; a campaign calendar; the ability to suspend or finish a campaign and have every open basket re‑priced within minutes; customer‑exclusive offers that travel with the customer; and, on every invoice, a line that says which offer gave what. Ninety‑one thousand of them, one calculator, and a penny we will not give back to the float.

<aside class="tldr bottom"><strong>In one paragraph</strong>Ninety-one thousand offers reduce to one calculator, one meter system, and one auditable row per discount — and even a single float rounding rule is enough to steal a penny across thousands of orders if you don't watch it.</aside>
