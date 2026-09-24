---
title: Sending a replacement
summary: Resend items from a dispatched order, give each one a reason that records who was at fault, and leave a note for the warehouse.
date: 2026-09-16
tags: dispatch, replacements, customers
category: dispatch
help_routes: grp.org.shops.show.ordering.orders.show.replacement.create, grp.org.shops.show.crm.customers.show.replacements.index
---

<aside class="tldr">
When something in a dispatched order arrives broken, goes missing or is wrong, you send the customer a <em>replacement</em>: a new delivery note for just the items being resent. Every item on a replacement needs a reason, and each reason is recorded against whoever caused the problem — the courier, the warehouse, the supplier or the customer — so there is a log of product faults and warehouse errors.
</aside>

## Starting a replacement

Open the order. Once it is **Dispatched**, a **Replacement** button appears in the page heading. Press it to open the replacement screen, which lists every item that went out on the order's delivery note.

## Choosing what to resend

Each line shows the **Quantity Dispatched** and a **Quantity Resend** box. Type how many of that item to send again — it cannot be more than was dispatched. **Replace All** fills every line with the full dispatched quantity when the whole order has to go again.

Lines left at zero are not part of the replacement.

## Giving a reason for each item

Every line with a quantity to resend needs a **Reason**. **Save** stays disabled until each of those lines has one.

| Reason | Recorded against |
|---|---|
| Damaged by courier | Courier |
| Lost by courier | Courier |
| Wrong item sent | Warehouse |
| Missing from parcel | Warehouse |
| Broken, poor packaging | Warehouse |
| Faulty product | Supplier |
| Customer error | Customer |
| Other | Not known |

Pick the reason that matches what really happened: it is how faults are counted later, not only a note for this order.

## Leaving a note for the warehouse

Above the items there is a **Note to warehouse** box. Use it for anything the pickers and packers should do differently this time — check the item before packing, add extra packaging, include something missing. The note is saved on the new replacement. Leave it empty to keep the order's own warehouse note.

## After saving

Pressing **Save** creates the replacement delivery note, which goes to the warehouse like any other note (see [Picking and packing a delivery note](/docs/picking-and-packing-a-delivery-note)). The reason for each item is shown next to its name on the replacement's delivery note page and on the picking screen, so the warehouse knows why it is going out again.

A customer's replacements are listed under their page in the shop's **CRM**. Replacements created before reasons were introduced have no reason shown.
