---
title: Handling items the warehouse could not pick
summary: When a picker cannot find an item, the order waits for customer service. Decide each line in Waiting for CRM, and see what will go back to the customer's balance.
date: 2026-09-29
tags: orders, picking, out of stock, balance, customer service
category: crm
help_routes: grp.org.shops.show.ordering.backlog.waiting_items
---

<aside class="tldr">
When a picker cannot find an item, they do not mark it out of stock themselves: they send the line to customer service with a note, and the order shows as <b>Waiting</b>. Open <b>Orders backlog</b>, click the red number on the <b>Waiting</b> box, and decide each line in <b>Waiting for CRM</b>: <b>Don't pick</b>, <b>Replace</b> or <b>Send back to waiting warehouse</b>. Only once a line is marked <b>Don't pick</b> does the order page show its value under <b>Marked out of stock so far</b>, and what is expected back to the customer's balance.
</aside>

## What happens in the warehouse

The picker goes to the location and the item is not there, or not enough of it. Instead of guessing, they send the line to customer service and usually leave a note, for example "Out of stock" or "Product on this location?".

From that moment:

- The delivery note, and the order, show as <b>Waiting</b> (you may also see it called handling blocked).
- On the order page the short line shows how many were picked next to how many were ordered, for example <b>2</b> struck through and <b>0</b>.
- The line is **not** marked out of stock yet. Nothing has been decided, so no amount is shown as coming back to the customer.

The product can still say, for example, <b>Stock: 16 available</b>. That is the figure on the books. The picker was at the shelf, so their report is what counts; the books are corrected when the location is checked.

## Where to find the lines to decide

1. Go to your shop → <b>Orders</b> → <b>Backlog</b>.
2. On the <b>Waiting</b> box, click the small red number. It counts the orders with items waiting for you.
3. The <b>Waiting for CRM</b> page lists one row per delivery note, with a link to its order.

Each line shows the SKO, how many are waiting, the product with its net and VAT-inclusive price, and the picker's note. A <b>Still on picking</b> label means the picker is still working on the rest of that delivery note.

## Deciding each line

<b>Don't pick</b> (red button with a skull). The item will not be sent and the customer will not be billed for it. Use it when the item really is not there. When nothing else on the delivery note is waiting, the warehouse can carry on with the order straight away.

<b>Replace</b>. Send another product instead, for example the same item in another colour. You can replace part of a pack with the broken glass switch under <b>Quantity to replace</b>; see [Changing part of an SKO on an order](/docs/changing-part-of-an-sko-on-an-order). Whatever you do not replace stays waiting for CRM.

<b>Send back to waiting warehouse</b>. Hand the line back to the warehouse, with a note, when you think it should be there: another location, a delivery just arrived, or the picker should look again.

If the customer should hear about it, contact them before you decide, so the decision matches what they want.

### When the line is part of a set

A product made of several parts, like a salt lamp with its bulb and cable, is refunded by the value of the part missing: a missing lamp refunds the lamp, not a third of the product.

If the product has **Sold only as a complete set** switched on (on its master's **Composition** page, or its own when it does not follow the master's parts), the other parts are never sent alone. After <b>Don't pick</b> on one part, the order stays <b>Waiting</b> until the warehouse puts the other parts back and presses <b>Parts put back</b> on the delivery note. The customer is then refunded the whole product.

## What the order page shows

On the order page, the payment box shows two extra lines once at least one line has been marked <b>Don't pick</b> while the order is still in the warehouse:

- <b>Marked out of stock so far</b>: the value, with VAT, of everything marked not picked on that order.
- <b>Expected back to balance when picking finishes</b>: how much of what the customer already paid will go back to their balance.

These lines do **not** appear while a line is still in <b>Waiting for CRM</b>. If you are looking at an order that is <b>Waiting</b> and there is no amount, check <b>Waiting for CRM</b> first: the line is waiting for your decision.

The order total itself stays at the submitted amount until picking is finished. After that the invoice is made for what was really sent and, on an order already paid, what was paid for the items not sent goes back to the customer's balance by itself.

## Before raising a ticket

If an amount looks missing or wrong on a waiting order, first check whether the line is still in <b>Waiting for CRM</b>. Ask a colleague or your manager, or search these guides. Raise a ticket when something is really broken.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Find lines waiting for you:</b> your shop → <b>Orders</b> → <b>Backlog</b> → red number on the <b>Waiting</b> box → <b>Waiting for CRM</b>.</li>
<li><b>Item really not there:</b> <b>Don't pick</b> on the line.</li>
<li><b>Send something else:</b> <b>Replace</b> on the line → choose the product and quantity → <b>Save</b>.</li>
<li><b>Warehouse should look again:</b> <b>Send back to waiting warehouse</b> → add a note → <b>Confirm</b>.</li>
<li><b>See the value out of stock:</b> open the order → payment box → <b>Marked out of stock so far</b>.</li>
</ul>
</aside>
