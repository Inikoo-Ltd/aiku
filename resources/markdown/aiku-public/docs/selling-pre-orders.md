---
title: Selling pre-orders
summary: How a product can be ordered beyond its stock, as a back-order or made to order, what the customer sees and pays, how the order waits for its goods, and how the balance and cancellations are handled.
date: 2026-09-28
tags: orders, shop, catalogue, procurement, payments
category: orders
series: Pre-orders
---

<aside class="tldr">
A <b>pre-order</b> lets a customer buy more than we have on the shelf. A <b>back-order</b> is a stock item that is temporarily out: it is paid in full and sent when the next delivery arrives. A <b>made-to-order</b> item is not stocked at all: we order it from the supplier when a customer buys it, and trade customers pay only a <b>deposit</b> at checkout. Nothing changes on a website until its shop's <b>Enable pre-orders</b> switch is on and a product is marked for it. The pre-order waits out of the warehouse until its goods arrive, then the customer pays what is left and it is sent.
</aside>

## Back-order and made-to-order

A product can be marked in two ways, and both are off until someone turns them on:

- **Back-order** — a product we normally stock. While it is out of stock, customers can still order it, and it is sent when the next delivery arrives. The estimated dispatch comes from the expected arrival of the open purchase order.
- **Made-to-order** — a product we do not keep in stock, such as furniture or statues from our suppliers abroad. We order it from the supplier when a customer buys it. The estimated dispatch comes from the lead time.

If both are on, the product is treated as made-to-order.

Products that are not marked behave exactly as before: when they are out of stock, they cannot be bought.

## Switching pre-orders on for a shop

Every term the customer accepts is a setting of the shop, so each website can have its own. They sit together under **Pre-orders** in the shop settings:

- **Enable pre-orders** — the switch for the whole shop. While it is off, marked products behave like any other product.
- **Default lead time** and **Dispatch estimate range** — the website shows a range in weeks, not a date. A lead time of 12 weeks with a range of 2 shows "estimated dispatch 12–14 weeks".
- **Made-to-order deposit** — the part paid at checkout on made-to-order items, 30% unless changed.
- **Pay in full below** — made-to-order orders worth less than this are paid in full, with no deposit.
- **Balance due within**, the two **balance reminder** days and **Cancel unpaid balance after** — what happens after the balance is asked for.
- **Free cancellation of made-to-order** — the working days a customer can cancel for free, until we place the supplier order.
- **Full refund when late by** — how late we can be before the customer may cancel with everything back.
- **Pallet delivery** limits, the **pallet quote tolerance** and a **pallet rate per country** — see below.

Money amounts are in the shop's own currency, so an EU shop sets its own equivalent of the UK amounts.

## Marking a product

Pre-orders are set on the **master product**, once, and copied to that product in every shop. A shop's own product has the same fields, but a change on the master replaces them.

Under **Pre-order** on the product you choose **Back-order** or **Made-to-order**, and optionally:

- a **Lead time** of its own, in days, instead of the supplier's;
- its own **Made-to-order deposit**, instead of the shop's;
- a **Maximum quantity per order**. Leave it empty for no limit.

The lead time comes from, in order: the product's own lead time, then the **pre-order lead time** of its preferred supplier, then the shop's default. A supplier's lead time can be typed in days or in weeks. For a back-order, a typed arrival date on an open purchase order or stock delivery comes first.

## What the customer sees and pays

On the product page and in the product lists the customer sees the type and the estimate, for example "Made to order · Estimated dispatch 9–11 weeks", along with the pre-order terms. The basket marks each pre-order line and lists the terms again.

At checkout the customer must **tick to accept the pre-order terms** before any payment is shown. The terms are the ones that apply to them, for example:

- the estimated dispatch time, and that it is an estimate;
- the deposit or payment terms and the cancellation terms;
- that handmade items vary in size, colour, grain and finish, and that dimensions are approximate;
- that pallet delivery is to the kerb only.

What is paid at checkout:

- **Trade, back-order** — paid in full, as normal.
- **Trade, made-to-order** — the deposit only, or everything when the order is worth less than the shop's threshold.
- **Dropshipping** — everything, always.

Pastpay and cash on delivery are not offered for a basket with pre-orders, and bank transfer is not offered when a deposit is due, because none of them can take a deposit now and the rest later.

The same terms are repeated in the order confirmation email and on the invoice.

## When a basket mixes stock and pre-orders

When the order is placed, the in-stock items and the pre-order items become **two orders**. The in-stock order goes to the warehouse now, with its normal delivery. The pre-order items form an order of their own, with their own delivery charge, which waits until the goods arrive. Each order shows a note pointing to the other.

The customer can instead tick **Hold my order and send everything together**: then nothing is split and the whole order waits for the pre-order goods.

The customer pays once at checkout. The part of that payment that belongs to the pre-order is moved to it through the customer's account balance, so the balance itself does not change.

## Waiting for the goods

A pre-order never goes to the warehouse on its own while it waits. On the order you will see a **Pre-order** panel with its state, the estimated dispatch dates, what has been paid and what is still due.

When stock of everything in the pre-order arrives, the pre-orders waiting for it take it, oldest first. From that moment the goods are **kept for them** and no longer offered on the website. Then:

- if nothing is left to pay, the order goes to the warehouse straight away;
- otherwise the customer is emailed a link to pay the **balance**, due within the shop's days. As soon as it is paid, the order goes to the warehouse by itself.

If the balance is not paid, the customer gets reminders on the shop's reminder days. After the shop's limit the order is cancelled, the deposit is kept, and the goods go back on sale.

If a back-order's purchase order now arrives later than the dispatch we promised, the customer is emailed the new dates automatically, with their cancellation options. When the dates of any other pre-order change, use **Change dispatch dates** on its panel and the customer is emailed the same way.

## Pallet delivery

Products heavier or longer than the shop's pallet limits are marked **pallet delivery**. The product page and basket show a rough estimate for the customer's country, for example "Estimated pallet delivery to Germany: approx. €150", taken from the shop's pallet rates.

- **Trade** — when the goods arrive, the order waits for staff to type the real cost with **Pallet quote**. The quote is sent with the balance request. If it is more than the shop's tolerance above the estimate, the customer may cancel and get their deposit back.
- **Dropshipping** — the estimate is charged with the order. Any difference to the final cost is invoiced or refunded after delivery.

## Cancelling

A customer can cancel from their order page, and sees how much comes back before confirming. Staff cancel from the pre-order panel and choose the reason. The refund goes to the customer's account balance.

- **Our failure to deliver** — we are later than the shop's limit past the estimated dispatch, the supplier cannot supply, the supplier's minimum order was not met, or the pallet quote is over the estimate: **everything is refunded**, deposit included, for trade and dropshipping.
- **Trade, back-order** — cancelled at any time before dispatch for a full refund.
- **Trade, made-to-order** — free until we place the supplier order, within the shop's working days. After that the made-to-order **deposit is kept** and the rest is refunded.
- **Dropshipping** — once the order is placed, payment is not refunded.

<aside class="wayfinder">

### Where to click in aiku

- **Switch pre-orders on for a shop** — open the shop, **Settings**, then **Pre-orders** › **Enable pre-orders**. The other terms are in the same section.
- **Mark a product** — **Masters**, open the master product, **Edit**, then **Pre-order**. A shop's own product has the same section under **Edit**.
- **Set a supplier's lead time** — open the supplier, **Edit**, then **Pre-orders**: **Pre-order lead time**, **Lead time in** (days or weeks) and **Order by date**.
- **Follow a pre-order** — open the order. The **Pre-order** panel at the top has **Supplier ordered**, **Goods arrived**, **Pallet quote**, **Change dispatch dates**, **Send to warehouse** and **Cancel pre-order**.
- **See every open pre-order by supplier** — see [Planning supplier orders for pre-orders](/docs/planning-supplier-orders-for-pre-orders).

### Permissions you need

- Changing a shop's settings needs **organisation admin** or **shop admin**.
- Marking products needs permission to edit the catalogue, on the master products or in that shop.
- The buttons on the pre-order panel need permission to edit orders in that shop.

</aside>
