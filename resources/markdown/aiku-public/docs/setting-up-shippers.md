---
title: Setting up shippers
summary: Add the carriers your warehouse dispatches with, connect the ones that print their own labels, and teach aiku which shipper to prefer for each destination.
date: 2026-08-31
tags: dispatch, shippers
help_routes: grp.org.warehouses.show.dispatching.shippers
---

<aside class="tldr">
A <em>shipper</em> is a carrier your warehouse hands parcels to — APC, GLS, DPD, Packeta, or the local courier down the road. You keep the list in the warehouse's <b>Dispatching → Shippers</b> screen, and in the organisation's <b>Settings</b> you can tell aiku which shipper it should suggest — or insist on — for each destination. After that, the dispatch screen mostly chooses for itself.
</aside>

## The shipper list

Every organisation keeps its own list of carriers. You find it inside the warehouse: open your warehouse, then **Dispatching → Shippers**. The **Current** tab shows the carriers in use today; the **Inactive** tab keeps the retired ones, so old shipments still know who carried them.

Each row shows the shipper's name, the name it trades under, and its **type** — which tells you whether aiku talks to that carrier directly (more on that below).

## Adding a shipper

Press **Create Shipper** at the top of the list. The form asks for four things:

- **Code** — a short internal reference, like `APC` or `GLS`.
- **Name** — the carrier's full name as your team knows it.
- **Trade as** — the short name that appears on shipments and paperwork.
- **Tracking url** — the carrier's tracking page. When someone types a tracking number in by hand, aiku uses this to build the link the customer can click.

That is all a basic shipper needs. From the moment it is saved, it can be chosen when dispatching a delivery note: the team picks the shipper, types the tracking number from the carrier's own system, and aiku keeps the record and the tracking link.

## Connected shippers: labels without typing

Some carriers do more than sit on a list. aiku can talk directly to **APC**, **GLS** (Slovakia and Spain), **DPD** (UK and Slovakia), **Packeta**, **CTT** and **ITD**. For a connected shipper, creating the shipment on the delivery note asks the carrier for a real consignment: the tracking number comes back by itself and the **shipping label is queued straight to the printer** — nobody types anything, nobody re-enters an address into a carrier website.

The connection needs account credentials from your carrier contract, so it is set up together with the aiku team rather than through the create form. If you open an account with one of the carriers above, ask for it to be connected — the difference at the packing bench is real.

## Preferred shippers: teaching aiku where each carrier is best

Most warehouses do not want packers deciding carriers parcel by parcel. In the organisation's **Settings**, under **Preferred Shipping**, you can write simple rules: *for this country — or this country and these postcodes — use this shipper*. A rule can apply to all your shops or be scoped to some of them.

Each rule can be gentle or firm:

- A normal rule makes the shipper the **suggestion**: the dispatch screen pre-selects it, but the team can still pick another.
- A rule marked **important** **locks** the shipper for those destinations. The dispatch screen will not let a packer quietly choose something else — overriding a lock takes a dispatching supervisor or an organisation admin, and even they get a warning first, because sending a parcel with the wrong carrier can mean the customer was charged the wrong shipping price.

Only active shippers count: a rule pointing at a shipper you have since retired simply stops applying.

## How the choice is made at dispatch

When a delivery note reaches the shipping step, aiku works out its suggestion in order:

1. **The customer's choice comes first.** If the order carries a shipper the customer locked in, that shipper is locked on the delivery note too.
2. Otherwise, if the order already has a shipper, or its shipping zone only ever uses one carrier, that one is suggested.
3. Otherwise, your **Preferred Shipping** rules are checked against the delivery country and postcode — the suggestion appears pre-selected, locked if the rule was marked important.

If nothing matches, the team picks from the shipper list as always. Either way, a connected shipper prints its own label, and a manual one asks for the tracking number.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>See or add shippers:</b> your warehouse → <b>Dispatching → Shippers</b> → <b>Create Shipper</b>. Retired carriers live under the <b>Inactive</b> tab.</li>
<li><b>Set preferred shippers:</b> your organisation → <b>Settings</b> → <b>Preferred Shipping</b> → add rules by country and postcode; tick <b>important</b> to lock one in.</li>
<li><b>Use them:</b> on the delivery note's shipping step the suggested shipper is pre-selected — confirm it, and connected carriers print the label by themselves.</li>
</ul>
</aside>
