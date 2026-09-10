---
title: Keeping the factory stocked
summary: The To restock page - which artefacts run out first, how long the factory takes to make anything, and how to put your own restock work on the To produce board.
date: 2026-09-09
tags: production, stock, planning
category: production
help_routes: grp.org.productions.show.to_restock
series: Ordering from partners
order: 11
---

<aside class="tldr">
For the person who plans the factory's week. <a href="/docs/fulfilling-partner-orders">To produce</a> answers <i>what has somebody asked for</i>. <b>To restock</b> answers the other question: <i>what will we run out of, whether or not anybody has asked yet</i>. It sorts everything the factory makes by how many days of cover is left, measured against how long this factory actually takes to make something, and lets you push what is worth making onto the To produce board.
</aside>

## Lead time is the yardstick

Every bucket on this page is measured in **lead times**, not in days. The lead time is the average number of days from work going on the floor to it coming back into the warehouse, taken from this factory's own job orders over the last year. Fewer than five finished job orders and there is nothing to measure, so aiku uses an estimate instead — seven days unless somebody sets another figure on the factory — and says *estimate* next to the number.

That is why the buckets read the way they do. An artefact with four days of cover is not in trouble in a factory that turns work around in two days; it is already lost in one that takes a week.

## The buckets

| Bucket | What it means |
| --- | --- |
| Out of stock | nothing on the shelf |
| Doomed | it will be gone before anything started today could arrive |
| Critical | out within two lead times |
| Danger | out within three lead times |
| Watch | out within four lead times |
| Covered | more than four lead times of cover |
| Dead stock | value on the shelf and no usage at all |
| Never made yet | an artefact with no stock record behind it |

Each bucket carries three numbers: how many artefacts are in it, how many are **already in hand**, and how many are **untouched**. In hand means somebody has already dealt with it — a line open on the To produce board, or a job order on the floor. Untouched is the number to work.

Click buckets to choose what the list below shows. It opens on **Out of stock, Doomed and Critical**, which is the honest morning list.

## The lanes

Below the buckets the same work is laid out in four lanes:

- **To do** — artefacts in the buckets you selected that nothing has been done about. Urgent first. Each row carries the stock code, what is on the shelf, days of cover, the artefact family, who usually makes it, and the number of **units** a job would be raised for: the recommended order quantity turned into units and rounded up to the next whole batch. Anything a partner has already asked for is left out — that is To produce's job, not this page's.
- **Queued** — lines already waiting on the To produce board, whether they came from a partner or from here.
- **Producing** — lines with a job order on the floor, with its reference and the artisan.
- **Restocked** — what came back from the floor in the last fortnight, so you can see the page working.

## Putting work on the board

Tick rows in **To do** and press the button to queue them. Each one becomes a line on the To produce board with no partner and no customer behind it: just work the factory owes itself. From there it is planned, assigned and made exactly like a partner line, and it leaves the board when the finished goods are put away.

A line is skipped, and says so, if the same stock is already open on the board. You cannot queue the same thing twice by pressing the button twice.

## Things worth knowing

- **On demand items are not here.** An artefact whose SKO is marked *On Demand* is made when it is asked for and has no cover to run out of.
- **Dead stock is a question, not a task.** Value sitting still with no usage usually wants a conversation with the shop, not a job order.
- **The page is worked out fresh.** Nothing is stored, nothing has to be tidied up, and a bucket empties by itself when the goods land.
- **Days of cover comes from the same forecast** the rest of aiku uses, see [How aiku predicts what you run out of](/docs/how-aiku-predicts-what-you-run-out-of).

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>The page:</b> your organisation → <b>Factory</b> → <b>To restock</b>.</li>
<li><b>Change what the lanes show:</b> click the bucket tiles at the top.</li>
<li><b>Queue your own work:</b> tick rows in <b>To do</b> → the queue button → they appear on <b>To produce</b>.</li>
<li><b>Set the estimated lead time</b> while history is thin: on the factory's own settings; once five job orders have been finished the measured figure takes over on its own.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permissions you need</strong>
<ul>
<li>Positions are set on the employee record under Human Resources and carry the rights with them.</li>
<li>Seeing the page: <b>Production operative</b> for the factory, or above.</li>
<li>Queueing work onto To produce: <b>Production floor supervisor</b> for the factory, or organisation supervisor.</li>
</ul>
</aside>
