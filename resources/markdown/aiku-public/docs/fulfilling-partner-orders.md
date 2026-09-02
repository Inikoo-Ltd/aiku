---
title: Working the To produce list
summary: The factory's guide - one queue of everything the factory owes, to partner organisations and to its own customers, grouped the way a production planner thinks.
date: 2026-09-02
tags: production, procurement, intercompany, dispatch
category: production
help_routes: grp.org.productions.show.partners
series: Ordering from partners
order: 4
---

<aside class="tldr">
For the people who <em>make things</em> and the person who plans the factory's day. <b>To produce</b> is the factory's queue: every line a partner organisation has asked for, plus every line an own customer has ordered that the factory has not got in stock. You group it by artisan, by category or by buyer, tick what you can send to partners, and the rest of the paperwork follows on its own. New to the partner flow? Start with the <a href="/docs/ordering-from-a-partner-organisation">overview</a>. Want the list to know who makes what? Read <a href="/docs/who-makes-what">Who makes what</a> first.
</aside>

## Where the lines come from

**Factory → To produce** is fed from two places. You never type a line here yourself.

- **Partner requests.** Sister organisations put what they need on their [shopping list](/docs/buying-from-a-partner). Every open line addressed to your factory appears here with the buyer, the quantity and the priority they set.
- **Own customers.** When an order is submitted in your own shop, aiku looks at each product. If the stock behind it is short and that stock is made by the factory, the shortfall lands here as a line, tagged with the customer and the order reference. When that order is dispatched the line closes by itself.

Orders that arrive through the old system do not feed the list. Only orders submitted in aiku do.

The **Source** filter at the top of the *All* tab lets you see only partner lines or only own-customer lines.

## The four views

The tab bar above the title is the whole point of the page. Same lines, four ways of looking at them.

- **All.** The flat table, sortable and searchable, with the count of open lines. Use it when you are looking for one thing.
- **By artisan.** One block per person, using the artisan attached to the artefact or, failing that, to its category. Lines with nobody attached sit under *Unassigned*. This is the view for handing out the day's work.
- **By category.** One block per artefact category, so the bath bomb maker sees bath bombs and the soap maker sees soap.
- **By buyer.** One block per partner organisation or own customer, for when you are building a shipment.

In the grouped views every block has a capsule above the list showing its name and line count. Click a capsule to hide that block, click again to bring it back. aiku remembers your choice in this browser, so a planner who only cares about two categories only ever sees two.

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
<li><b>See the queue:</b> your organisation → <b>Factory</b> → <b>To produce</b>. Switch views with the tabs <b>All · By artisan · By category · By buyer</b>.</li>
<li><b>Hide a block:</b> in a grouped view click its capsule above the list. Click again to show it.</li>
<li><b>Only partners or only customers:</b> <i>All</i> tab → <b>Source</b> filter.</li>
<li><b>Ship to a partner:</b> tick lines → <b>Pick into order</b> → <b>Send to warehouse</b> in the <i>Picked orders</i> box.</li>
<li><b>Decide who makes what:</b> see <a href="/docs/who-makes-what">Who makes what</a>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permissions you need</strong>
<ul>
<li>Seeing the list: view rights on the factory's operations or procurement, or organisation supervisor.</li>
<li>Picking and sending: orchestrate rights on the factory's operations, or organisation supervisor.</li>
</ul>
</aside>
