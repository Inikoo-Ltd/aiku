---
title: Raising a purchase order and receiving the goods
summary: Buy from an ordinary supplier - raise the purchase order, get it confirmed, then turn the delivery into stock you can sell.
date: 2026-09-01
tags: procurement, purchase orders, stock deliveries, suppliers
category: procurement
help_routes: grp.org.procurement.org_suppliers, grp.org.procurement.purchase_orders, grp.org.procurement.stock_deliveries
---

<aside class="tldr">
When you buy from an ordinary supplier - not a partner organisation, which has its own guide - the job runs in two stages. First you raise a <b>purchase order</b> and get the supplier to confirm it. Then, when the goods turn up, you record a <b>stock delivery</b> against that order and check it in until the stock is placed on your shelves. This article covers both, plus what each state button along the way actually does.
</aside>

## Suppliers and agents

Every supplier your organisation buys from directly lives in **Procurement → Suppliers**. Each supplier's page gives you a **Purchase Order** button to start a new order, plus a side menu with **Products**, **Purchase Orders** and **Stock Deliveries** so far.

Some suppliers are only reachable through an **agent** - a person or company who buys on your behalf rather than shipping to you directly. Agents have their own list under **Procurement → Agents**, and work the same way: purchase orders and deliveries against an agent are recorded on the agent's page instead of the supplier's.

## Raising a purchase order

From the supplier's page, press **Purchase Order**. This creates a new order in the **In process** state - it exists, but nothing has been sent to the supplier yet.

While it is in process:

- Use **Add Product** to add a line for a product you want, one at a time.
- Each line can be adjusted while the order is still in process.
- **Delete** removes the whole order, as long as nothing has been sent to the supplier yet.

Once you have added everything you want, press **Submit**. This sends the order onward and moves it to **Submitted**.

## What the states mean

A purchase order moves through a short, deliberate chain:

- **In process** - you're still building the order. Add products, submit, or delete it.
- **Submitted** - the order has gone to the supplier. You can **Confirm** it once the supplier has agreed to it, **Undo Submit** to pull it back to In process if something needs changing, or **Cancel** it altogether.
- **Confirmed** - the supplier has accepted the order. You can set or change the **Delivery date** (the estimated arrival), and press **New Delivery** to create the stock delivery that will receive the goods. Until a delivery exists for it, you can also **Undo Confirm** to send it back to Submitted.

From here the order settles on its own as its stock deliveries progress - there's nothing further to click on the purchase order itself. It ends up **Settled** once everything has arrived, or **Not Received**/**Cancelled** if it didn't work out.

## The stock delivery: recording what arrived

Pressing **New Delivery** on a confirmed purchase order creates the stock delivery for you, already linked to that order's lines. You can also start one from scratch under **Procurement → Stock Deliveries**, which just asks for a delivery **number** and **date**.

A stock delivery's page has tabs for its **Items**, the **Pending Items** still to deal with, **Done Items**, **Attachments**, and **History**.

The delivery then moves through its own states:

- **In process / Confirmed / Ready to ship** - while it's still on its way, you can press **Mark as Dispatched** once the supplier has sent it, **Mark as Received** if it's already arrived, or **Delete** if it was created by mistake.
- **Dispatched** - the parcel is on the road. **Mark as Received** once it lands at your warehouse, or **Unmark as Dispatched** to undo that if it hasn't actually left.
- **Received** - the goods are physically at the warehouse. From here you check each item against what was ordered; the delivery becomes **Checked** once that's done, or you can **Unmark as Received** or **Cancel** the whole delivery.
- **Checked** - if nothing has been placed into stock yet, you can still **Cancel** here.
- **Booking in / Booked in** - the checked quantities are being booked into the warehouse's stock.
- **Booked in** - press **Place** to put the received stock away. This is the delivery's final working state.

Checking an item means confirming how much of each line actually arrived - not every order arrives complete, and short or extra quantities show up on the **Under/Over delivered items** tab so nothing gets lost in the gap between what you ordered and what showed up.

## Putting it together

In short: raise the order against the supplier, submit it, wait for the supplier to confirm, then create the delivery from the confirmed order. Mark the delivery dispatched when the supplier ships it, received when it lands, work through checking each item, and finally place it - at which point the stock is in the warehouse and ready to sell.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Find a supplier or agent:</b> your organisation → <b>Procurement → Suppliers</b> (or <b>Agents</b> for agent-managed suppliers).</li>
<li><b>Start a purchase order:</b> on the supplier's page, press <b>Purchase Order</b>; add lines with <b>Add Product</b>, then <b>Submit</b> when ready.</li>
<li><b>Move it forward:</b> on the order's page, use <b>Confirm</b>, <b>Undo Submit</b>, or <b>Cancel</b> while submitted; once confirmed, set the <b>Delivery date</b> and press <b>New Delivery</b>.</li>
<li><b>Receive the goods:</b> on the stock delivery's page, work through <b>Mark as Dispatched → Mark as Received</b>, check the <b>Items</b> tab, then <b>Place</b> once it's booked in.</li>
<li>You can also start a delivery from scratch under <b>Procurement → Stock Deliveries</b>.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permissions you need</strong>
You need permission to view procurement for the organisation to see purchase orders and stock deliveries, and permission to edit procurement to raise, submit, confirm or otherwise change them.
</aside>
