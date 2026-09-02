---
title: Preparing mixes
summary: For the preparer and the planner - how a mix or base becomes something the factory tracks, how the Mixes tab works out what to prepare, and how the preparer's job orders flow.
date: 2026-09-02
tags: production, crafts
category: production
help_routes: grp.org.productions.show.partners.mixes, grp.org.productions.show.crafts.raw_materials
series: Ordering from partners
order: 6
---

<aside class="tldr">
For the person who prepares mixes and bases before the artisans can start, and for the planner who sends them the work. A mix is made in-house, so aiku treats it as both a <b>raw material</b> (the artisans consume it) and an <b>artefact</b> (the preparer makes it). Once linked, the <b>Mixes</b> tab on <a href="/docs/fulfilling-partner-orders">To produce</a> works out how much of each mix is needed from the open job orders, and one button turns that into job orders for the preparer. Setup for categories and artisans is in <a href="/docs/who-makes-what">Who makes what</a>.
</aside>

## Why a mix is two things

A bath bomb recipe says "0.5 kg of base mix per unit". That base mix is not bought, it is prepared in the factory from its own ingredients. So it lives twice:

- As a **raw material**, so recipes can consume it and stock is deducted when the finished product is received.
- As an **artefact**, with its own recipe and its own job orders, so the preparer has work to do and a batch to receive into stock.

The link between the two is one field on the raw material: **Made in-house as**. Set it to the mix's artefact. That is the whole setup.

## Setting up a mix

1. **Create the artefact** for the mix under **Factory → Crafts → Artefacts**, with its recipe steps and its own raw materials, like any other artefact. Give it a stock (SKU) so received batches have somewhere to go.
2. **Create or open the raw material** for the mix under **Factory → Crafts → Raw materials**. Edit it, set **Made in-house as** to the artefact from step 1, and give it the same stock (SKU).
3. **Use the raw material in recipes.** On each product that needs the mix, add the mix to the right recipe step with the quantity per unit.
4. **Attach the preparer** to the mix artefact, or to a category holding all the mixes, under *Usually made by*. Job orders for mixes then go to that person.

## The Mixes tab

**Factory → To produce → Mixes** lists every in-house raw material that an open job order needs. A job order is open from the moment it is created until it is received into stock.

For each mix you see:

- **Needed**: open job order quantities multiplied by the recipe's quantity per unit, added up across products.
- **On hand**: the stock of the mix right now.
- **Being made**: the quantity in open job orders for the mix itself.
- **Short**: needed minus on hand minus being made. Short lines come first and are shown in red.
- **Needed for**: the product codes that consume it, so the preparer knows what is waiting.

Tick the mixes to prepare, adjust the quantity if the shortfall is not the right batch, and press **Create job orders**. One job order per preparer is created, addressed to them, in draft. Open it and press *Release to floor* when it should start.

## What the preparer does

The preparer runs their own line, so they hold the <b>Production floor supervisor</b> position for the factory, the same as the planner. That lets them open the Mixes tab, create and release their own job orders, and receive them into stock, without waiting for anyone. On the floor they work like any artisan: their tasks appear on the floor screen, they press START and DONE, and when the last step is done the job order is received into stock with a batch code. From that moment the mix shows as on hand and the artisans' products can be made.

If the preparer is not paid by piece rate, that is a payroll setting, not a reason to skip the floor. The record of who prepared which batch and when is what gives traceability from the finished product back to its ingredients.

## Things worth knowing

- A mix cannot need itself. If the mix artefact's own recipe lists the same raw material, that line is ignored.
- The Mixes tab only reads job orders in this factory. A product made in another factory does not create demand here.
- "Being made" counts a job order until it is received into stock, even if every task is done. Receive job orders promptly and the numbers stay honest.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Link a mix:</b> <b>Factory → Crafts → Raw materials</b> → open the mix → <b>Edit</b> → <b>Made in-house as</b>.</li>
<li><b>See what to prepare:</b> <b>Factory → To produce → Mixes</b>.</li>
<li><b>Send the work:</b> tick mixes → <b>Create job orders</b> → open the job order → <b>Release to floor</b>.</li>
<li><b>Do the work:</b> <b>Factory → Floor</b> (My tasks) → <b>START</b> / <b>DONE</b>; then the job order is received into stock from its page.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permissions you need</strong>
<ul>
<li>Positions are set on the employee record under Human Resources and carry the rights with them.</li>
<li>Seeing the Mixes tab and working the floor: <b>Production operative</b> for the factory, or above.</li>
<li>Creating, releasing and receiving job orders, and linking a raw material to its artefact: <b>Production floor supervisor</b> for the factory, or organisation supervisor. The preparer needs this one.</li>
</ul>
</aside>
