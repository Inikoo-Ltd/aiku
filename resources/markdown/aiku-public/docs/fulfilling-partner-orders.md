---
title: Working the To produce list
summary: The factory's guide - one queue of everything the factory owes, to partner organisations and to its own customers, grouped the way a production planner thinks.
date: 2026-09-09
tags: production, procurement, intercompany, dispatch
category: production
help_routes: grp.org.productions.show.to_produce
series: Ordering from partners
order: 4
---

<aside class="tldr">
For the people who <em>make things</em> and the person who plans the factory's day. <b>To produce</b> is the factory's queue: every line a partner organisation has asked for, plus every line an own customer has ordered that the factory has not got in stock. The <b>Board</b> is where you plan: drag a line across the lanes to decide how many to make and who makes it, and a job order is created for the artisan. The list views group the same lines by artisan, category or buyer, and from there you tick what you can send to partners; the rest of the paperwork follows on its own. New to the partner flow? Start with the <a href="/docs/ordering-from-a-partner-organisation">overview</a>. Want the list to know who makes what? Read <a href="/docs/who-makes-what">Who makes what</a> first.
</aside>

## Where the lines come from

**Factory → To produce** is fed from two places. You never type a line here yourself.

- **Partner requests.** Sister organisations put what they need on their [shopping list](/docs/buying-from-a-partner). Every open line addressed to your factory appears here with the buyer, the quantity and the priority they set.
- **Own customers.** When an order is submitted in your own shop, aiku looks at each product. If the stock behind it is short and that stock is made by the factory, the shortfall lands here as a line, tagged with the customer and the order reference. When that order is dispatched the line closes by itself.

Orders that arrive through the old system do not feed the list. Only orders submitted in aiku do.

The **Source** filter at the top of the *All* tab lets you see only partner lines or only own-customer lines.

## The views

The tab bar above the title is the whole point of the page. Same lines, six ways of looking at them.

- **Board.** The planning view, and the one the page opens on. Each line is a card that moves through lanes from *Backlog* to *Done*. Explained in the next section.
- **All.** The flat table, sortable and searchable, with the count of open lines. Use it when you are looking for one thing.
- **By artisan.** One block per person, using the artisan attached to the artefact or, failing that, to its category. Lines with nobody attached sit under *Unassigned*. This is the view for handing out the day's work.
- **By category.** One block per artefact category, so the bath bomb maker sees bath bombs and the soap maker sees soap.
- **By buyer.** One block per partner organisation or own customer, for when you are building a shipment.
- **Mixes.** The bases and mixes the open job orders need, for the preparer. Explained in [Preparing mixes](/docs/preparing-mixes).

In the grouped views every block has a capsule above the list showing its name and line count. Click a capsule to hide that block, click again to bring it back. aiku remembers your choice in this browser, so a planner who only cares about two categories only ever sees two.

## The Board

Six lanes, left to right. A card moves right as the work progresses, and most moves are a drag.

| Lane | What sits there |
| --- | --- |
| Backlog | lines with an artefact that nobody has looked at yet |
| Preparing | lines you have decided to make, with the quantity settled |
| Assigned | a job order exists and is addressed to an artisan, but nobody has started it |
| Producing | an artisan has pressed START on one of its tasks |
| Done | every task on the job order is done; it waits for the warehouse to put it away |

Each card shows the product, the quantity asked for, who asked, and **In stock** so you can see whether it is worth making at all. Only lines with an artefact in this factory reach the board; lines the stock can simply be walked off the shelf for live on their own page, **Factory → Pre-pick**, see [Gathering a partner's goods](/docs/gathering-a-partners-goods).

Under the lanes sits one more line: **N lines too small for a batch are waiting for company · show**. A partner may ask for less than one full batch, and that line cannot sensibly be made on its own, so it waits at the roadside instead of cluttering the Backlog. It is picked up when open demand for the same stock across every partner list reaches a batch, when an own-customer order at the gate makes the job run anyway, or when you press *show* and make it regardless. A line may wait a long time; that is a truer description of its situation than any status we could invent for it. See [Batches, packs and part batches](/docs/batches-packs-and-part-batches).

**Backlog → Preparing.** Drop the card and aiku asks *How many to make?*. It proposes the quantity asked for; type more and the extra is marked *for stock*. If the artefact has a recommended batch size, a small **↑** button rounds the quantity up to full batches. What the artisan is finally asked for is in **units**, rounded up to the next whole batch, and what comes back is divided by the pack size on its way to the shelf: 16 units of a ten-pack land as 1.6 SKOs. The number stays editable on the card while it is in Preparing.

**Preparing → Assigned.** Drop the card and aiku asks *Who makes it?*. It proposes the artisan attached to the artefact or its category, see [Who makes what](/docs/who-makes-what). Pick a name and a job order is created in draft, addressed to that person. Open the job order and press **Release to floor** when it should start; until then the artisan does not see it. To change the artisan later, click the name on the card.

**Producing** and **Done** move on their own from what happens on the floor screen. A card leaves the board when the warehouse puts the finished goods away, see [Putting away finished production](/docs/putting-away-finished-production), or when the job order is received into stock from its own page.

Several cards at once: click the cards to select them, then drag any one of them and the whole selection moves. The **Everybody** menu above the board narrows it to one or two artisans, and the family, buyer and priority filters do the same for the cards.

The factory sidebar carries the live counts for **To produce** and **Pre-pick** beside their names, and they move on their own as shopping lists change; no need to reload the page to see whether anything new came in.

Under the Board and the By artisan view sits **Open job orders per artisan**: one chip per person with how many job orders they have open. Red means none, amber means one; everyone should have at least two so nobody runs dry. The cross on a chip marks the person as not an artisan and hides them from the count.

## Sending partner lines

Partner lines are shipped from here; own-customer lines are not, they travel with their own order.

- Tick the partner lines you can send. Adjust the quantity for a **partial pick**, the remainder stays open for a later shipment.
- **Pick into order** gathers your ticks into a pending shipment per buying organisation. It stays open in the *Picked orders* box until you send it.
- **Send to warehouse** hands the shipment to your warehouse as a normal order: picked, packed, dispatched and invoiced like everything else. The buying organisation's incoming stock delivery is created for them and follows your warehouse's progress. Nobody updates the buyer's side by hand.

Ticking an own-customer line does nothing useful. It is skipped when you press Pick into order, because that product already belongs to a customer order.

## Things worth knowing

- A buyer's open list is capped at about one order cycle of what you historically deliver to them, so what reaches you is a filtered request, not a catalogue dump. If a line looks strange, ask; the buyer gave something up to put it there.
- The first pick for a new partner creates a customer account named after the buying organisation in your shop. Expected. Warn customer services so nobody "cleans it up".
- Until you press Send to warehouse the picked order is invisible on ordinary order screens; the To produce page is its home.
- What you dispatch is what the buyer's stock delivery says. Never pad quantities to "match the list".

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>See the queue:</b> your organisation → <b>Factory</b> → <b>To produce</b>. Switch views with the tabs <b>Board · All · By artisan · By category · By buyer · Mixes</b>.</li>
<li><b>Decide the quantity:</b> <i>Board</i> → drag the card from <b>Backlog</b> to <b>Preparing</b> → type the number, or press <b>↑</b> for full batches.</li>
<li><b>Create the job order:</b> drag the card from <b>Preparing</b> to <b>Assigned</b> → pick the artisan → open the job order → <b>Release to floor</b>.</li>
<li><b>Lines that only need picking:</b> <b>Factory</b> → <b>Pre-pick</b>, its own page.</li>
<li><b>Small lines waiting for a batch:</b> press <b>show</b> on the line under the board.</li>
<li><b>What is running out anyway:</b> <b>Factory</b> → <b>To restock</b>, see <a href="/docs/keeping-the-factory-stocked">Keeping the factory stocked</a>.</li>
<li><b>Hide a block:</b> in a grouped view click its capsule above the list. Click again to show it.</li>
<li><b>Only partners or only customers:</b> <i>All</i> tab → <b>Source</b> filter.</li>
<li><b>Ship to a partner:</b> tick lines → <b>Pick into order</b> → <b>Send to warehouse</b> in the <i>Picked orders</i> box.</li>
<li><b>Decide who makes what:</b> see <a href="/docs/who-makes-what">Who makes what</a>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permissions you need</strong>
<ul>
<li>Positions are set on the employee record under Human Resources and carry the rights with them.</li>
<li>Seeing the list: <b>Production operative</b> for the factory, or above.</li>
<li>Moving cards on the Board, creating and releasing job orders, picking and sending: <b>Production floor supervisor</b> for the factory, or organisation supervisor. The <b>Mix preparer</b> can do the same for mixes only.</li>
</ul>
</aside>
