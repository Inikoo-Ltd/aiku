---
title: Pre-pick
summary: The factory guide to Factory → Pre-pick - which partner lines land there, what each status means, and what to do when the stock is there, when only part of it is, when more is on its way, and when it will never come.
date: 2026-11-25
tags: production, procurement, intercompany, warehouse
category: production
help_routes: grp.org.productions.show.pre_pick
series: Ordering from partners
order: 8
---

<aside class="tldr">
Lines that need making go to <b>To produce</b>; lines you already have on the shelf come to <b>Pre-pick</b>. For each Pre-pick line:
<ul>
<li><b>You have all the stock:</b> do nothing. aiku sends it to the warehouse on its own within ten minutes. If the stock covers it exactly, with none left over, it waits: press <b>Pre-pick</b>.</li>
<li><b>You have part of it, and more is on its way from a supplier:</b> wait. Once the delivery is booked into stock, aiku sends the whole line on its own. Or press <b>Pre-pick</b> to send what you have now; the rest goes to <b>To produce</b>.</li>
<li><b>You have part of it, and it is something you make:</b> waiting does nothing, because nobody is asked to make the rest. Press <b>Pre-pick</b> to send what you have; the rest goes to <b>To produce</b> to be made.</li>
<li><b>It will not come (discontinued, not available):</b> press <b>Close short</b> to send what there is and cancel the rest; the partner is told. If none is in stock, the line is on <b>To produce</b>: see Case 3 below.</li>
</ul>
</aside>

<figure><img src="/art/docs/draw-pre-pick-fork.svg" alt="Watercolour sketch: a partner's shopping-list line reaches a signpost pointing to To produce, a workbench where things are made, or to Pre-pick, a shelf of stock. From the shelf, all in stock goes on its own to the partner's bay within ten minutes, part in stock goes to the bay while the rest waits for a delivery truck, and stock that will not come gets a red Close short stamp with a note flying back to the partner." width="1200" height="750" loading="eager"><figcaption>On the shelf: Pre-pick. Not on the shelf: To produce.</figcaption></figure>

## Why a line lands here and not in To produce

A partner's order arrives as lines on a shopping list. For each line aiku looks at your **free stock**: what is on the shelf minus what is already promised to other partner lines.

- **The free stock covers none of it** - the line goes to **To produce**, to be made. See [Working the To produce list](/docs/fulfilling-partner-orders).
- **The free stock covers some or all of it** - the line comes to **Factory → Pre-pick**.

So the same order can be split across both pages: the tea you blend goes to To produce, the bottles, bags and boxes you already hold come here. A line whose stock runs out later moves to To produce by itself.

Lines are served in a queue, per product: **urgent** first, then **high**, **normal** and **low**; within the same priority, the one needed soonest, then the oldest. A line only counts the stock left after every line ahead of it.

## Reading the list

| Column | What it tells you |
| --- | --- |
| For | which partner organisation asked |
| Artefact | the code and name of the product |
| To | that partner's gathering bay, where the goods will go |
| Asked | how much the partner asked for |
| In stock | what is on your shelves now |
| Can pick | what you can give this line now, after the lines ahead of it in the queue |
| Shortfall | what is missing to cover the whole line |
| Status | what aiku is going to do with it, see below |
| Priority, Added | where the line sits in the queue |

Category, requester and urgency filter the list; the numbers on the filters are the real counts.

## What the status means

| Status | What it means | What you do |
| --- | --- | --- |
| **Going to the warehouse** | the stock covers the whole line, with some left over | nothing - within ten minutes aiku pre-picks it on its own and it leaves the list |
| **Held, one more needed on the shelf** | the stock covers the line exactly, with nothing left over | nothing, or press **Pre-pick** if it should go now; aiku only sends a line by itself when at least one stays on the shelf |
| **Awaiting full stock, production manager to decide** | only part of the line is in stock | decide, see the cases below |
| **In To produce** | there is stock, but the lines ahead of this one have taken it | nothing here; the line is waiting on the To produce page |

Pre-picking is not shipping. It promises the stock to that partner and puts a walk on the warehouse's list: they take it off the shelf and put it in the partner's bay. The goods leave when the partner's next shipment goes, on a schedule or when somebody presses **Send to warehouse**. See [Gathering a partner's goods](/docs/gathering-a-partners-goods).

## Case 1: you have all the stock

Do nothing. The line shows **Going to the warehouse** and aiku pre-picks it within ten minutes. If it shows **Held, one more needed on the shelf** and it should not wait, press **Pre-pick** on the row.

To send several lines at once, tick them and press **Pre-pick selected**, or **Pre-pick all** for everything the current filters show.

## Case 2: you have part of the stock, or more is on its way

The line shows **Awaiting full stock, production manager to decide**. You have three choices:

- **Wait for the rest.** Do nothing. When goods you have ordered from a supplier arrive and are booked into stock, the free stock grows; once it covers the whole line with at least one left on the shelf, aiku pre-picks it on its own. Choose this when the delivery is close and the partner would rather receive it all in one go. Waiting only works when stock is really coming: for a product you make, nobody is asked to make the rest while the line waits here, so it would wait for ever.
- **Send what you have now, the rest later.** Press **Pre-pick** (the quantity shows as a *partial pick*). The part in stock goes to the bay; the rest stays on the partner's list as its own open line and moves to **To produce**. A product you make appears there on the board, to be made; anything else appears on the **All** tab, and comes back here once new stock arrives.
- **Send what you have and stop there.** Press **Close short**. aiku asks you to confirm, sends what is in stock and cancels the rest. The partner's list drops to the quantity sent, and the person who added the line and the partner's purchasing team are told it was closed short, so they can buy it elsewhere.

## Case 3: the stock will not come

The product is discontinued, the supplier no longer has it, or it is not available for a long time.

- **Some of it is in stock** - press **Close short**: the partner gets what there is, and is told the rest will not come.
- **None of it is in stock, and you make it** - the line is a card on the **To produce** board. Drag it out of **Backlog** (to **Preparing**, or to **Producing** if your factory uses the simple board) and, in the box that opens, press **Can't be done** and give the reason. The line leaves the board, and the person who added it and the partner's purchasing team are told, with your reason, so they can buy it elsewhere.
- **None of it is in stock, and you do not make it** - the line is on **To produce → All**, but there is no **Can't be done** button there yet. Ask the partner to delete the line from their shopping list, or raise a ticket.

Do not leave such lines waiting with no word: the partner sees them as **Requested** on their list and keeps counting on them.

## What the partner sees

On their own shopping list every line says where it has got to: **Requested**, **Being made**, **Pre-picked**, **Staged for you**, **Being picked**, **On its way**.

When a line is closed short or can't be done, the people on the partner's side are told, with the reason.

## Things worth knowing

- **Nothing reserves stock until it is pre-picked.** A line that says *Awaiting full stock* has not set anything aside: its stock can still be sold or given to another line. If it must not be taken, pre-pick what is there.
- **If the shelf turns out short** - an audit finds less, or it was sold elsewhere - aiku takes back the promise from the newest pre-picks first, and those lines return to the list or to To produce.
- **The list is worked out fresh each time you open it.** Lines leave by themselves once pre-picked; there is nothing to tidy up.
- **The number beside Pre-pick** in the factory sidebar is how many lines are waiting, and it updates on its own.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>The list:</b> your organisation → <b>Factory</b> → <b>Pre-pick</b>.</li>
<li><b>Send a line now:</b> <b>Pre-pick</b> on the row, or tick rows and press <b>Pre-pick selected</b>, or <b>Pre-pick all</b>.</li>
<li><b>Send what is there and cancel the rest:</b> <b>Close short</b> on the row.</li>
<li><b>A line you make that can't be made:</b> <b>Factory</b> → <b>To produce</b> → drag the card out of <b>Backlog</b> → <b>Can't be done</b>.</li>
<li><b>Lines with no stock that you don't make:</b> <b>Factory</b> → <b>To produce</b> → <b>All</b> tab.</li>
<li><b>Walks to the bay:</b> <b>Warehouse</b> → <b>Dispatching</b> → <b>Pre-pick</b> tab, see <a href="/docs/gathering-a-partners-goods">Gathering a partner's goods</a>.</li>
<li><b>Send the gathered goods:</b> <b>Factory</b> → <b>To produce</b> → <i>Picked orders</i> → <b>Send to warehouse</b>, or let the partner's shipment schedule do it.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permissions you need</strong>
<ul>
<li>Positions are set on the employee record under Human Resources and carry the rights with them.</li>
<li>Seeing the list: any factory position that can view production, or organisation supervisor.</li>
<li>Pre-picking and closing short: a factory position that plans or prepares production, a procurement editor, or organisation supervisor.</li>
<li><b>Can't be done</b>: a factory position that plans or prepares production, or organisation supervisor. See <a href="/docs/factory-positions">Factory positions</a>.</li>
</ul>
</aside>
