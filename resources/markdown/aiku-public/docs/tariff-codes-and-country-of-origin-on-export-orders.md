---
title: Tariff codes and country of origin on export orders
summary: See where customs data lives on a delivery note, why weight and value are split between the parts of a multi-part product, and why the tab can legitimately differ from the invoice.
date: 2026-09-12
tags: dispatch, customs, tariff codes
category: dispatch
help_routes: grp.org.shops.show.ordering.orders.show.delivery-note
---

<aside class="tldr">
Every delivery note has a <b>Tariff codes / Origin</b> tab, next to <b>Items</b>. It lists what is physically in the boxes, one row per tariff code and country of origin, with the units, weight and value that go with it. You can export it to Excel for customs paperwork. If a row is missing data it sits pinned at the top so you can fix it fast. Here is how to read the tab, why a multi-part product can look odd on it, and why it does not have to match the invoice.
</aside>

## Where to find it

Open a delivery note — either from your warehouse's **Dispatching → Delivery notes** list, or from an order's own **Delivery notes** tab — and you will see **Tariff codes / Origin** sitting next to **Items**. Each row groups everything on the note that shares the same tariff code and the same country of origin: the code itself, its description, the origin flag, whether it is flagged as dangerous goods (DG), the parts it covers, any UN numbers, and the total units, weight and value.

## Why it counts by part, not by product

A product can be made of more than one part. Take a face roller sold with its own pouch: that is two separate SKOs (stock keeping items) under the hood, each with its own tariff code. The tab lists each part under its own code, with its own share of the units and weight. A pouch sold on its own, and the same pouch sold inside the roller set, both end up under the pouch's tariff code — because that is what customs cares about: what is actually in the box, not how you sold it.

The same logic applies to weight and units: they are worked out per part, not per whole product.

## Where the amount comes from

Value is trickier, because an order line is priced as a whole product, not part by part. To split it, aiku uses the best basis all the parts on that line share: each part's own selling price if every part has one, otherwise supplier cost, otherwise stock value, and only as a last resort an equal split. Each part gets its fair share, and the shares add up to the line total. If a line's totals move without cause, a part's price or cost changing is usually why.

## When something is missing

If any part lacks a tariff code or country of origin, aiku cannot place it, so it pins a row at the top with a marker for missing tariff code or origin, listing exactly which trade units are the problem. Both fields live on the trade unit, so that is where you fix them: **Goods → Trade units**, open the trade unit, fill them in and save. Every delivery note referencing it will pick up the change.

An organisation can also fine-tune a tariff code without touching the shared 6-digit HS code every organisation uses: it can add or change the last few national digits on top of it, for its own customs rules, without disturbing the shared code for anyone else. The tab shows that override once it exists.

## Getting it out as a spreadsheet

Press **Export** and you get an Excel file with the same grouping you see on screen. Before it downloads you can choose which columns to include: tariff code, description, origin, UN numbers, references (the part codes), weight in kilograms, units and amount. Pick only what the paperwork needs.

## Why this can differ from the invoice

The invoice has its own "Group by Tariff Code" layout and export, which groups by whole product: a multi-part product goes under its main part's code, carrying its whole price. The delivery note tab splits the same product across each of its parts and their own codes instead.

Both are correct — they answer different questions. The tab answers "what is physically in these boxes, under which customs code", which is what export paperwork needs. The invoice answers "what did the customer buy, for how much", a commercial document. A multi-part product's value or weight can legitimately differ between the two; that is not a mismatch to fix.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>See tariff codes and origin for a shipment:</b> open the delivery note (from your warehouse's <b>Dispatching → Delivery notes</b>, or from the order's own <b>Delivery notes</b> tab) → <b>Tariff codes / Origin</b> tab, next to <b>Items</b>.</li>
<li><b>Export the tab as a spreadsheet:</b> on the <b>Tariff codes / Origin</b> tab, press <b>Export</b> and choose the columns you need.</li>
<li><b>Fix a missing tariff code or origin:</b> your organisation → <b>Goods → Trade units</b>, open the trade unit, and fill in the tariff code and country of origin.</li>
<li><b>See the invoice's own tariff-code grouping:</b> open the order's <b>Invoices</b> tab → open the invoice → the <b>Group by Tariff Code</b> option.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permissions you need</strong>
To view a delivery note's <b>Tariff codes / Origin</b> tab you need dispatching or fulfilment view access for that warehouse, or view access to the shop's orders. Changing a trade unit's tariff code or country of origin, or setting an organisation's national-digits override, needs accounting edit access.
</aside>
