---
title: Sending a thousand products is like sending none
summary: A dashboard that makes ordering easy invites dumping. What stops the purchaser ticking everything red and calling it someone else's problem - a hard cap built from measured deliveries, a shared warehouse split fairly, and a rule about never letting anyone edit the number that judges them.
date: 2026-08-31
tags: procurement, inventory, product-design, forecasting
---

<aside class="tldr"><strong>TL;DR</strong>We rebuilt the screen where one organisation buys from a sister organisation. The hard part was not showing what is running out — that is a query. The hard part was <em>restraint</em>: an open shopping list is capped at one order cycle of what that partner measurably delivers to us, warehouse space is a shared resource split into per-partner fair shares, and every number that limits a person is measured from history and uneditable — or honestly labelled a guess. A-rank and out-of-stock items are exempt, so an emergency always fits.</aside>

## The lazy-purchaser trap

The first version did the obvious thing well. It coloured the partner's catalogue by how close each product was to running out, sorted the worst first, and put a plus button on everything. Then someone asked the question that killed it:

*What stops the purchaser saying "I just don't care", ticking everything red and yellow, and making it the seller's problem?*

Nothing did. And the failure is worse than the obvious one. A thousand-line shopping list is not an over-order that gets trimmed — it is **a list with no signal in it**. The seller looks at it and cannot tell the two A-rank crises from the five hundred and ninety-four D-rank shrugs. Sending a thousand products to the producer is like sending none.

That is the general shape of the thing: automation without counter-pressure produces irresponsibility at scale. Making an action cheap makes the *thinking* optional, and the thinking was the whole job.

## Three answers, one chosen

**Soft shame** — colour it red, show a "you are over budget" warning, let them through. Ignored within a week; every warning that can be ignored eventually is.

**Manager approval** — a queue and a second person. Real friction, but it buys restraint by spending somebody else's day, and it moves the judgement to the person with less information.

**A hard cap with a forced trade-off** — the list simply cannot exceed a number, so adding one more thing means removing something. We took this one. The trade-off thinking is exactly what laziness skips, so the UI performs it: at the cap, the add is refused and you must decide what matters less. You cannot buy your way out with volume.

## The cap has to be a fact, not an opinion

Then the question is what number. The first instinct — *their production capacity* — is wrong twice over. We do not know it, and it is not ours: the seller supplies three sister organisations plus their own customers. Our share is a fraction we have never negotiated.

So we stopped measuring them and measured **ourselves**: what this partner has historically **booked into our warehouse**, per month, over the last six months. That number already contains our share of their output, the allocation they have quietly decided, and every real-world constraint neither side wrote down. It needs three months of history to be trusted; below that we fall back.

The first fallback was embarrassing and worth admitting. We already have a [demand forecaster](/blog/stop-pretending-you-are-forecasting) with a recommended order quantity per SKU, so we summed it, priced it, and got **£583,000** for a partner turning over a fraction of that. The forecaster was not wrong — it rounds every recommendation up to the supplier's pack size, and a thousand-unit bag of candy rounds a demand of forty into a thousand. Sum a few thousand of those and you have fabricated most of a million pounds. A number produced by an honest process can still be nonsense once you aggregate it, and the only way to find out is to look at the total and ask whether you believe it.

The replacement is dumber and defensible: **what we actually dispatched of that partner's products in the last ninety days**, at their prices, per month. Real shipments — no pack-size rounding, no out-of-stock extrapolation. It landed within 2% of the delivery-measured figure where both existed, and it says something a purchaser can argue with: *don't buy faster than you sell*.

One last correction: a shopping list is not a month of demand sitting open at once. It should hold **one order cycle** — the lead time plus a review week — so the monthly figure is scaled by that fraction.

## The warehouse is not yours

Money is only half the ceiling. Space is limited, not infinite, and one partner is not the only supplier: one supplier can fill the warehouse with slow products and the rest of the supply base cannot come in.

So warehouse space became a second, *shared* constraint, and it only bites where it should — on **products we have never stocked**. Something already on the shelf refills its own slot and passes freely. New products face two gates: a warehouse-wide floor (under 5% of locations free and nobody adds new products, from any supplier) and a **per-partner fair share** of what is free — about a fifth. Not because a fifth is optimal, but because the alternative is first-come-first-served, which is the same lazy-purchaser problem wearing a different hat.

We wanted to express this in cubic metres. Every `volume` on the trade units is null and every `max_volume` on the locations is null — the data does not exist. So a location is one slot, the screen says slots, and nobody pretends otherwise. Building on the data you actually have is cheaper than building on the data you wish you had and discovering the difference in production.

## The honesty rule

The same rule got applied twice, to the lead time and to the budget, and it is the piece I would keep if I had to throw the rest away:

> **If we have the history, the number is measured, shown as measured, and cannot be edited. If we don't, we guess, we say it is a guess, and the guess is editable — in settings, never inline.**

Measurement displaces the guess automatically once enough samples exist, and when it does, the edit field disappears from the form. The lead time is the average from ordering to booked-in over a year of real deliveries; below five samples it is a per-product estimate you can type in. The budget went the same way, after the editable version was killed twice with the same argument: *who are they to edit the partner's capacity? They can put twenty million in there.* A cap you can raise is not a cap; it is a text field with a moral.

The subtle part is where the edit lives. Inline on the dashboard, an estimate becomes a lever you pull when the screen says no. In the product's settings, it is a data-quality task you do when you know something the system does not.

## How will they process that in their little brains

The other half was making restraint legible to someone with a warehouse to run. The rule we settled on: **every tile answers exactly one question — how many still need me?**

Not a percentage, not a fraction, not a coverage ratio. "654 need action", where *action* means it is not on the list and nothing is on the way, so the number falls to zero as the work gets done. Under it, the same count split by revenue rank, A first, D and Z faded at the end, because two out-of-stock A products outrank five hundred D ones and the sort order should say so without a training session.

The buckets are sized by the **measured lead time**, not by calendar weeks — the tiers are two, three and four lead times of remaining cover. The one worth naming is the first: **doomed** — items that still have stock but are mathematically gone before any delivery could land, even if ordered this second. A calendar-week bucket cannot express that. It is also the tile that changes behaviour, because it is the only one that says *you already lost this one; here is what to do about the next.*

And every tile closes its own loop: the number opens the list of items, a rank opens them in the partner's catalogue, **+ fill** opens the auto-fill proposal already scoped to that bucket and already generated — the purchaser adjusts and confirms. More work than one magic button, and much more control, which is the trade the whole screen is making.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>Cap: <code>stock_deliveries.cost_total</code> for the partner over 6 months ÷ distinct months (≥3 required), else 90 days of <code>delivery_note_items.quantity_dispatched</code> × the partner's price ÷ 3; both scaled by <code>(lead_days + 7) / 30</code>. Cached 15 minutes.</li>
<li>One guard on every add path — manual, bulk, auto-fill — with A-rank/out-of-stock exemption; the suggester stops proposing at the cap rather than offering lines that cannot be committed.</li>
<li>Warehouse: empty <code>locations</code> as a slot proxy; <code>&lt; 5%</code> free blocks new products globally, <code>20%</code> of free slots is one partner's share, measured against open list lines for never-stocked items.</li>
<li>Lead time: average <code>submitted_at</code> → <code>booked_in_at</code> per item, promoted to columns on <code>supplier_products</code> and <code>org_stocks</code> by a hydrator that fires on booking in; the estimate column is only editable while <code>measured_lead_time_days</code> is null.</li>
<li>Buckets: a single SQL <code>case</code> over <code>days_of_cover</code> against each row's own lead time, grouped by health rank, so eight tiles and their rank rows are one query over the partner's whole catalogue.</li>
</ul>
</aside>

## What it cost

More refusals. A purchaser occasionally cannot add the thing they wanted and has to decide what to drop, which is a worse thirty seconds and a better order. The seller gets a list they can read top to bottom, where the top actually means something. And the numbers that judge people are measured from what those people did, which is the only version of a limit anybody accepts twice.

<aside class="tldr bottom"><strong>In one paragraph</strong>Ease without counter-pressure is not productivity, it is noise at scale. Cap the list with a number measured from reality, split the shared resources fairly before anyone fills them, make every limiting number either measured-and-locked or honestly-labelled-and-editable, and ask each tile one question a tired person can answer.</aside>
