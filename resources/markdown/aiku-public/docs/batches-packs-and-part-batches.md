---
title: Batches, packs and part batches
summary: The factory makes units in whole batches, the warehouse counts packs. What packed_in and the batch size do to a job order, to what lands on the shelf, and to what a partner should order.
date: 2026-09-09
tags: production, stock, procurement, crafts
category: production
help_routes: grp.org.productions.show.crafts.artefacts.show
series: Ordering from partners
order: 12
---

<aside class="tldr">
Two counts meet at every artefact and they are not the same count. The floor works in <b>units</b> - one bath bomb, one bar of soap - and can only sensibly make a whole <b>batch</b> of them, because that is what the mixer holds. The warehouse and the shop work in <b>SKOs</b>: the pack the goods are sold and stored in, ten to a box. <b>Packed in</b> is the only bridge between the two, and aiku now crosses it at both ends instead of pretending the numbers are interchangeable.
</aside>

## The three numbers

- **Batch size** — how many units the factory makes at once. It belongs to the artefact, set on its page or in bulk from the artefacts list, see [Changing many artefacts at once](/docs/changing-many-artefacts-at-once).
- **Packed in** — how many units go in one SKO. It belongs to the SKO in the warehouse.
- **Units and SKOs** — the floor is asked for units, everything else is counted in SKOs.

## What happens at each end

**Raising a job.** Whatever was asked for — a partner line, an own-customer shortfall, a restock line — is a quantity in SKOs. aiku multiplies it by *packed in* to get units, then rounds **up** to the next whole batch. Ask for 1 SKO of a ten-pack made in batches of 16 and the artisan is asked for 16 units, not 1 and not 10.

**Receiving the finished job.** What the artisan made is units, and it is divided by *packed in* on the way to the shelf. Those 16 units of a ten-pack land as 1.6 SKOs: one sealed box and six loose. The shelf figure is honest about the remainder rather than rounding it away.

## When a batch does not fill whole packs

A batch of 16 and a box of 10 never come out even. That is not an error and aiku does not treat it as one: the factory sizes a batch for the mixer, the shop sells in packs, and both are right. It is simply worth knowing where it happens, so it is measured:

- a **Batch in SKOs** column and a **Batch not whole SKOs** filter on the artefacts list;
- a line on the artefact page showing the batch in SKOs and the nearest batch size that would come out whole;
- a stat on the crafts dashboard counting the artefacts where it happens.

Nothing forces you to change a batch size. If the suggestion is easy to take, take it and the arithmetic stops leaving remainders. If the mixer decides the batch, leave it.

## What a partner should order

The same arithmetic decides the neatest order quantity, which aiku calls the **order step**: the smallest number of SKOs that whole batches fill exactly. For a batch of 16 units in packs of 10 it is 8 SKOs — eighty units, five batches, no remainder.

On the partner's side, in the [shopping list](/docs/buying-from-a-partner) and the stock list:

- the line says *made in batches of N units* and, where they differ, *full batches every N SKO*;
- a small button rounds the quantity up to the next step;
- **suggested** quantities and everything Auto-fill proposes are already on the step;
- an order off the step is still accepted, with a note that a full batch is made either way, so the order may be delayed or the quantity adjusted.

An order below one full step cannot be made on its own at all. On the [To produce](/docs/fulfilling-partner-orders) board it waits behind *lines too small for a batch are waiting for company* until enough demand turns up, an own-customer order makes the job run anyway, or the planner decides to make it regardless.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Set a batch size:</b> your organisation → <b>Factory</b> → <b>Crafts</b> → <b>Artefacts</b> → open the artefact, or tick several and set it from the selection bar.</li>
<li><b>Find the awkward ones:</b> the artefacts list → filter <b>Batch not whole SKOs</b>, or the <b>Batch in SKOs</b> column.</li>
<li><b>See the suggestion:</b> the artefact page, under the batch size.</li>
<li><b>Set packed in:</b> <b>Warehouse → Inventory</b> → open the SKO → <b>Edit SKO</b>.</li>
<li><b>Order on the step:</b> partner shopping list → the button next to <i>full batches every N SKO</i>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permissions you need</strong>
<ul>
<li>Positions are set on the employee record under Human Resources and carry the rights with them.</li>
<li>Batch size and shelf life on artefacts: the factory's <b>research and development</b> right, or organisation supervisor.</li>
<li>Packed in on a SKO: a warehouse position that can edit inventory.</li>
</ul>
</aside>
