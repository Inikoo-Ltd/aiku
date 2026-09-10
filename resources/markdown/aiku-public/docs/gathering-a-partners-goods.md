---
title: Gathering a partner's goods
summary: The warehouse guide - what pre-pick means, why stock in a partner's gathering bay stops counting as available, and how to work the Pre-pick list in Dispatching.
date: 2026-09-09
tags: dispatch, procurement, intercompany, warehouse
category: dispatch
help_routes: grp.org.warehouses.show.dispatching, grp.org.productions.show.pre_pick
series: Ordering from partners
order: 8
---

<aside class="tldr">
For the warehouse. Some of what a partner organisation asks for is not made here at all - bottles, bags, boxes, sticks. Nothing to produce: somebody just has to walk it off the shelf and put it in that partner's bay. That walk is called <b>pre-pick</b>, the bay is a <b>goods out gathering location</b>, and the moment stock is in it, it stops counting as available to anyone else. Your list of walks to do is <b>Dispatching → Pre-pick</b>.
</aside>

## Why pre-pick exists

A partner organisation's request does not become an order straight away. It sits on a list until somebody at this end acts on it, and only becomes an order, a delivery note and a stock delivery when it is sent to the warehouse.

That leaves a gap. A partner asks for 490 bottle-and-lid sets today, they will not ship for another week, and in the meantime nothing stops those bottles being sold or used elsewhere. Nothing in aiku holds stock back on its own - not a request, not an order, not even a delivery note. The only thing that truly reserves stock is moving it somewhere it cannot be taken from.

That is what a **goods out gathering location** is for: an ordinary location in the warehouse, marked as a gathering point for one partner. What sits in it is theirs.

## What the two words mean

- **Pre-pick** - taking the goods off the shelf early and putting them in that partner's bay, before the order goes anywhere. On the factory side it also means "we are not making this one, take it from stock".
- **Goods out gathering location** - a location marked so that whatever is in it stops counting as available. The stock is still ours, still counted, still valued, still audited. It is simply spoken for.

## The list of walks to do

**Warehouse → Dispatching → Pre-pick.**

Each row is one walk:

| Column | What it tells you |
| --- | --- |
| For | which partner organisation the goods are for |
| SKO | the code and name of what to fetch |
| From | the location the system suggests, the one holding the most |
| To | that partner's gathering bay |
| Staged | how much is already in the bay |
| To move | how much still has to be walked over |

Fetch the goods, put them in the bay, then press **Moved**. That records the move in aiku, the row disappears by itself, and the quantity drops out of availability.

The list is worked out fresh every time you open it - it is not a set of tasks somebody has to tick off or tidy up. If the stock is already in the bay, the row is simply not there. If somebody adds more to the request, a row comes back.

The tab only appears for organisations that have a gathering bay set up for a partner. If you cannot see it, that has not been done yet.

## The factory's half of the same job

The walks come from somewhere: somebody at the factory has to decide that a line is taken from stock rather than made. That decision has its own page, **Factory → Pre-pick**, and it is the twin of the list above.

It lists every open partner line with stock behind it, whether or not this factory makes that artefact, and never shows a line already pre-picked. Each row carries the requester, the artefact, what was **asked** for, what is **in stock**, and what **can pick** - the two capped against each other, so a line is never promised more than exists. Category, requester and urgency filter the list, and the counts on the filters are the real counts, not just what fits on the page.

**Pre-pick** on a row, **Pre-pick selected** for what you ticked, or **Pre-pick all** for everything the current filters show. Pre-picking promises the stock to that partner and puts the walk on the warehouse's list; where only part of what was asked for is available the line splits, the promised part goes and the rest stays open. Nothing is sold and no order is created - the stock simply stops being available to anybody else.

The number beside **Pre-pick** in the factory sidebar is how many lines are waiting for that decision, and it updates on its own.

## What the partner sees

Nothing to tell them by hand. On their own shopping list every line carries where it has got to: **Requested**, **Being made**, **Pre-picked**, **Staged for you**, **Being picked**, **On its way** - with the job order, order or delivery note reference beside it.

**Staged for you** means exactly what you did: their goods are in their bay, waiting for the next dispatch.

## When it actually ships

Gathering is not shipping. The goods leave when somebody sends the picked order to the warehouse, on the **To produce** page, which turns the collected requests into an order, a delivery note and a stock delivery on the partner's side. See [Working the To produce list](/docs/fulfilling-partner-orders).

Because the goods are already in one bay, the picking at that point is a walk to a single location instead of a tour of the warehouse.

## Things worth knowing

- **A gathering bay is not storage.** Anything left in one is invisible to everybody else - it will not be offered to a picker and it will not show as available to sell. Put stock there only when it is going to that partner.
- **Marking an existing bay changes numbers immediately.** If a location already holds stock when it is marked as a gathering point, that stock leaves availability at once. Check what is in a location before it is marked.
- **Nothing else reserves.** Two people can be told the same units are free until one of them is walked into a bay. If something must not be sold from under a partner, move it.
- **Moving it back releases it.** Take stock out of the bay, or take the gathering mark off the location, and the quantity returns to availability.
- **One bay per partner** is the usual arrangement, named after the partner so a picker can read it at a glance.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Decide a line is taken from stock:</b> your organisation → <b>Factory</b> → <b>Pre-pick</b> → <b>Pre-pick</b> on the row, or tick and use <b>Pre-pick selected</b> / <b>Pre-pick all</b>.</li>
<li><b>The list of walks:</b> your organisation → <b>Warehouse</b> → <b>Dispatching</b> → <b>Pre-pick</b> tab.</li>
<li><b>Record a walk:</b> press <b>Moved</b> on the row after the goods are physically in the bay.</li>
<li><b>Check what is in a bay:</b> <b>Warehouse</b> → <b>Locations</b> → the location → <b>SKOs</b> tab.</li>
<li><b>Mark a location as a gathering point:</b> <b>Warehouse</b> → <b>Locations</b> → the location → <b>Overview</b> → <b>Goods out gathering</b>.</li>
<li><b>Point a partner at their bay:</b> not a screen - ask an administrator, it is set from the console on purpose so it cannot be changed by accident.</li>
<li><b>Send the gathered goods:</b> <b>Factory</b> → <b>To produce</b> → <i>Picked orders</i> → <b>Send to warehouse</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permissions you need</strong>
<ul>
<li>Positions are set on the employee record under Human Resources and carry the rights with them.</li>
<li>Seeing the list and recording a move: a dispatching position for the warehouse, or organisation supervisor.</li>
<li>Marking a location as a gathering point: a warehouse position that can edit locations.</li>
<li>Pointing a partner at a bay: administrator, from the console.</li>
</ul>
</aside>
