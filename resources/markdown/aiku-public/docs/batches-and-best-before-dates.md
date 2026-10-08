---
title: Batches and best-before dates
summary: How aiku knows which batch of each product sits on which shelf and when it expires, what each team does to keep it right, and where to see what is about to expire.
date: 2026-10-08
tags: warehouse, batches, best-before, goods in, picking, production, reports
category: warehouse
help_routes: grp.org.warehouses.show.inventory.batch_codes.index
---

<aside class="tldr">
Every batch that reaches a shelf carries its code and best-before date, and every pick takes it off again, so aiku always knows which batches are on which shelf. Most of it is automatic. Goods in types the code and date from the label of supplier deliveries, production records the shelf life of what it makes, and the warehouse labels the stock that was already on the shelves once. Then <b>Inventory → Batch Codes</b> and the SKO export show what is about to expire.
</aside>

## How a batch travels

A batch is a code and a best-before date for one SKO. Stock carries its batch from the moment it arrives until it leaves:

- **Supplier deliveries:** goods in records the batch at checking, and placing puts it on the shelf. See [Raising a purchase order and receiving the goods](/docs/raising-a-purchase-order-and-receiving-the-goods).
- **Our own production:** a finished job order gets a batch when it is put away, with the best-before typed for the run or worked out from the shelf life of the artefact. See [Putting away finished production](/docs/putting-away-finished-production).
- **Deliveries from a partner organisation:** the batches the partner picked arrive filled in on your stock delivery. See [Buying from a partner](/docs/buying-from-a-partner).
- **Picking:** each pick takes the batch with the earliest best-before on that location, and splits across batches when it has to. See [Picking and packing a delivery note](/docs/picking-and-packing-a-delivery-note).
- **Returns and cancelled picks** go back on the shelf as the batches that were picked.

Stock with no batch recorded is treated as the oldest on the shelf, so it is used first.

## What each team does

| Who | What to do | How often |
|---|---|---|
| Goods in | At **Check**, press **+ Batch** under the checked quantity and type the batch code and best-before from the label. Split with **Add batch** when one line came in several batches. | Every supplier delivery |
| Goods in, partner deliveries | Confirm the batches already filled in, correct them if the goods say otherwise. | Every partner delivery |
| Pickers | Nothing, unless you took a different batch than the one shown: then change it on the pick. | When it happens |
| Production | Fill in the shelf life of each artefact (or a whole family at once). Without it, made goods get a batch but no best-before. | Once, then for new artefacts |
| Warehouse | Label the stock already on the shelves: on the SKO's **Batch Codes** page, **Count batches**, count each location as printed on the goods and save. Start with food, aromas and cosmetics. | Once per SKO |
| Stock family owner | Switch **Batch tracked** on (stock family edit page) for families where best-before matters. Their goods in lines then warn until every SKO has a batch. | Once per family |

Nothing ever blocks: goods without a printed code can still be booked in and picked. They simply show as *without batch* in the reports, which is the cue to fix them.

## Seeing what is about to expire

- **Inventory → Batch Codes** lists the batches on the shelves, earliest best-before first, with the SKOs left, the number of locations and the days left: amber within 90 days, red once expired. A batch with no best-before is flagged.
- An SKO's **Batch Codes** page shows the same for that SKO, and is where you count it batch by batch.
- The SKO export (**Inventory → SKOs**, export) adds, for each SKO, the earliest best-before on the shelves, the SKOs already expired, those expiring within 30 and within 90 days, and the SKOs without a batch.
- The delivery note PDF lists batch, best-before and quantity under each item.

More on locations and stock checks: [Warehouse areas, locations and stock](/docs/warehouse-areas-locations-and-stock).

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Batches about to expire:</b> your warehouse → <b>Inventory → Batch Codes</b>.</li>
<li><b>Enter batches at goods in:</b> the stock delivery → <b>Items</b> → <b>+ Batch</b> under the checked quantity.</li>
<li><b>Label stock already on the shelves:</b> <b>Inventory → SKOs</b> → the SKO → <b>Batch Codes</b> → <b>Count batches</b>.</li>
<li><b>Turn on batch tracking:</b> <b>Goods → Families</b> → the family → edit → <b>Batch tracked</b>.</li>
<li><b>Export best-before per SKO:</b> <b>Inventory → SKOs</b> → export.</li>
</ul>
</aside>
