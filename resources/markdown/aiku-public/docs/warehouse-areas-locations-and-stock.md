---
title: Warehouse areas, locations and stock
summary: Understand how a warehouse is divided into areas and locations, how to create a new location, and how stock is placed and moved between locations.
date: 2026-09-01
tags: warehouse, locations, stock
category: warehouse
help_routes: grp.org.warehouses.show.infrastructure.warehouse_areas, grp.org.warehouses.show.infrastructure.locations, grp.org.warehouses.show.inventory.org_stocks
---

<aside class="tldr">
A warehouse is broken down into <b>areas</b> — sections such as Goods In or a picking zone — and each area holds a set of <b>locations</b>, the individual shelves or bins where stock actually sits. Every SKO (stock keeping object) is placed in one or more locations, and you move it between them, or correct its count, from the location's own screen.
</aside>

## Warehouse areas

Open your warehouse and go to **Locations → Areas** in the left navigation. The list shows every area's name, its picking order, how much stock it holds in value, how many locations sit inside it, and how many of those locations are empty.

Press **Areas** at the top of the list to create a new one. The form asks for:

- **Code** — an internal reference, up to 16 characters.
- **Name** — what the area is called.
- **Picking position** — an optional number that sets where this area falls in the picking order.

Open any area and you land on its **Overview** tab, with **Locations** and **History** tabs alongside it. The page header shows how many locations the area contains, and from here you can create a new location directly inside that area.

## Locations

Locations are listed under **Locations → Locations** in the warehouse's left navigation. The list can be filtered to **All**, **Empty**, or **Partially empty** locations, and each row shows the location's code, its maximum weight, its maximum volume (in cubic metres), how many stock slots it has, and how many of those slots are empty.

### Creating a location

Press the create button and fill in:

- **Code** — the location's reference.
- **Max weight (kg)** — the most this location should hold, by weight.
- **Max volume (m³)** — the most it should hold, by volume.

A location can be created directly under the warehouse, or inside a specific area — either way it ends up on the warehouse's location list.

### What a location's page shows

Opening a location takes you to its **Overview** tab, which shows its maximum weight and volume, how many stock slots it has in total, and how many are empty. Depending on how the location is set up, further tabs appear:

- **SKOs** — the stock currently held in this location, when the location is allowed to hold stock.
- **Pallets** — any pallets sitting in the location, when the location is used for fulfilment.
- **Stock movements** — every quantity change recorded against this location, when the location is allowed to hold stock.
- **History** — a log of edits made to the location itself.

A location can be switched on or off for holding ordinary stock, for fulfilment pallets, or for dropshipping — the tabs above only appear when the matching setting is on.

## Stock (SKOs)

Every product held in the warehouse is tracked as a SKO. The warehouse-wide list sits under **Inventory → SKOs**, showing each SKO's reference, family, name, how many are in stock, their stock value, potential sales, stock on the way from suppliers, and how many days of cover the current stock gives you.

A SKO's own stock is spread across one or more locations — the same SKO can sit in several bins at once, each with its own quantity.

## Moving stock between locations

From a location's **SKOs** tab you can move stock out of that location in two ways:

- **Move All SKO** — moves every SKO currently in the location to another location you choose, all at once.
- **Partialy Move SKO** — select specific SKOs from the list first, then move just those to another location; you also choose whether to remove each SKO from the original location once it has been moved.

Both forms ask you to pick the **destination location** and a **transfer reason** (for example, a warehouse transfer, a picking-error correction, or a wrong-bin correction), with an optional note. Moving stock this way keeps the SKO's quantity in each location accurate and logs the movement, so it appears on both locations' **Stock movements** tab.

## Stock checks and audits

When a SKO's stock across the whole warehouse falls below the warehouse's low stock threshold, it appears on the **Low Stock Audits** screen, reached from the **Low Stock Audits** tile on the warehouse's **Inventory** dashboard. This list shows the SKO's reference, its family, its name, its current stock, and the locations it is held in — it is the list staff work through to recount and confirm stock.

Auditing a location's stock records the difference between what the system expects and what you actually counted: you enter the counted quantity and a reason (recount, count gain, count shortage, damaged, expired, and others), and aiku logs the adjustment as a stock movement against that location and marks the stock as checked.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>See or add warehouse areas:</b> your warehouse → <b>Locations → Areas</b> → <b>Areas</b> button to create one.</li>
<li><b>See or add locations:</b> your warehouse → <b>Locations → Locations</b>, or from inside an area's <b>Locations</b> tab — use the create button, or <b>New location</b> on an area's page.</li>
<li><b>See warehouse stock:</b> your warehouse → <b>Inventory → SKOs</b>.</li>
<li><b>Move stock between locations:</b> open a location → <b>SKOs</b> tab → <b>Move All SKO</b> or select rows and use <b>Partialy Move SKO</b>.</li>
<li><b>Check stock:</b> your warehouse → <b>Inventory</b> dashboard → <b>Low Stock Audits</b> tile.</li>
</ul>
</aside>
<aside class="permissions"><strong>Permissions you need</strong>
<p>Viewing areas, locations and stock needs inventory or locations view access for the warehouse. Creating or editing areas and locations, and moving stock between locations, needs locations edit access (or the locations supervisor role) for that warehouse.</p>
</aside>
