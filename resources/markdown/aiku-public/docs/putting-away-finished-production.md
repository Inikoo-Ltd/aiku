---
title: Putting away finished production
summary: The warehouse guide - where finished job orders appear, how aiku works out whether they go to a partner's bay or to normal stock, and how to book them in with one code.
date: 2026-09-08
tags: dispatch, production, intercompany, warehouse
category: dispatch
series: Ordering from partners
order: 10
---

<aside class="tldr">
For the warehouse. When the artisans finish a job order it does not become stock by itself - somebody has to carry it somewhere and say where. That list is <b>Dispatching → From production</b>. Each row tells you who the goods are for and suggests the location: a partner's gathering bay when the whole job is for one partner, otherwise a stock location you type in. Press <b>Put away</b> and the goods are booked into that location with a batch code.
</aside>

## Where the rows come from

A job order appears here the moment every task on it is marked DONE on the floor, and stays until it is put away. Nobody has to send it to you.

Job orders still being worked on are not here. If you need to see what is coming, the factory's **To produce** board has a **Done** column with the same job orders - see [Working the To produce list](/docs/fulfilling-partner-orders).

## Reading a row

| Column | What it tells you |
| --- | --- |
| Job order | the reference, JOxxx-0001 |
| Artisan | who made it |
| Made | how many of what - 20 × SKO-01, one line per product |
| For | the partner organisation's code, or *Stock* |
| To location | the location it should go to |

**For** is worked out from the requests behind the job order. If every line was asked for by the same partner organisation, the goods are theirs and the row says so, with that partner's gathering bay already filled in under **To location**. That bay is the same one the [pre-pick list](/docs/gathering-a-partners-goods) uses: whatever sits in it is spoken for and stops counting as available to anyone else.

If the job order was made for stock, for an own customer, or for more than one partner, the row says *Stock* and the location box is empty. Type the code of the location you are putting it in.

## Putting it away

1. Carry the goods to the location shown, or to the one you chose.
2. Check the code in **To location**. Change it if you put the goods somewhere else.
3. Press **Put away**.

That books the goods into the location, gives them a batch code made from the job order reference and the product code, deducts the raw materials the recipe says were used, and marks the job order as received. The row disappears, and on the factory board the line leaves the **Done** column.

The quantity booked in is what the artisans actually made, not what was asked for. A job order that asked for 25 and got 19 books in 19.

## What happens next

- **Partner goods** sit in the partner's bay until someone on the **To produce** page sends the picked order to the warehouse. Picking is then a walk to one bay. The partner sees the line as *Staged for you* on their shopping list.
- **Own customer goods** go into normal stock and the waiting delivery note is released for picking on its own, since the shortage that was holding it is gone.
- **Stock** simply becomes available.

## Things worth knowing

- **The tab only appears** for organisations that have a factory.
- **One job order, one location.** If a job order really has to be split between two places, put it away into stock and let the pre-pick list move the partner's share.
- **A wrong location is fixed like any other stock error** - move the stock between locations. The job order itself is not reopened.
- **Nothing here is reserved until it is in a bay.** Goods put into ordinary stock can be picked for anyone.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>The list:</b> your organisation → <b>Warehouse</b> → <b>Dispatching</b> → <b>From production</b> tab.</li>
<li><b>Book it in:</b> check <b>To location</b> → <b>Put away</b>.</li>
<li><b>See what is in a bay:</b> <b>Warehouse</b> → <b>Locations</b> → the location → <b>SKOs</b> tab.</li>
<li><b>What the factory still owes:</b> <b>Factory</b> → <b>To produce</b> → <b>Board</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permissions you need</strong>
<ul>
<li>Positions are set on the employee record under Human Resources and carry the rights with them.</li>
<li>Seeing the list and putting away: a dispatching position for the warehouse, or organisation supervisor.</li>
</ul>
</aside>
