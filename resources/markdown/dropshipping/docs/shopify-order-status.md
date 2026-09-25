---
title: Your Shopify orders and their status
summary: How orders from your Shopify store reach us, how they are paid, what each status means, why an order can wait as Submitted or not arrive at all, and what we send back to Shopify.
date: 2026-09-25
tags: shopify, orders, status, payment, submitted, unpaid, fulfilment request
category: orders
series: shopify
order: 4
help_routes: retina.dropshipping.customer_sales_channels.orders.index, retina.dropshipping.customer_sales_channels.orders.show, retina.dropshipping.customer_sales_channels.show, retina.dropshipping.mit_saved_cards.dashboard
shops: awd, dssk, dse
---

<aside class="tldr">
When a customer buys a connected product in your Shopify store, Shopify sends us a fulfilment request and the order appears in your channel's <b>Orders</b>. We pay it from your balance, then from your saved card. A paid order goes to our warehouse by itself. An order we could not pay stays <b>Submitted</b> and <b>Unpaid</b> until you pay it. When we send it, we mark it fulfilled in Shopify with the tracking number.
</aside>

## How an order reaches us

1. A customer buys one of your connected products in Shopify.
2. Shopify sends a fulfilment request for those items to the <b>aiku-</b> location.
3. We accept the request and create the order in your channel. You find it under your channel, <b>Orders</b>.

Only products that are connected in <b>My Products</b> can come to us. If an order has some of our products and some of your own, we accept our products and you send the rest yourself.

## How the order is paid

We try to pay each new order straight away:

1. First with your balance.
2. If the balance is not enough, with the cards saved under <b>Saved Cards</b>, in your order of priority.

If the payment works, the order goes to our warehouse by itself. If it does not, the order waits and we send you an email saying it is on hold. Most of the time this happens because no card is saved. To stop it happening again, save a card: see [Payment cards and options](/docs/payment-cards-and-options).

## Pay an order that is waiting

An order we could not pay shows <b>Unpaid</b> next to its number and stays <b>Submitted</b>.

1. Top up your balance with at least the amount due, from <b>Top Up</b> in the menu.
2. Open your channel, <b>Orders</b>, and open the order.
3. Press <b>Pay ... with balance</b>. The button shows only when your balance covers the amount due.

The order then goes to our warehouse by itself.

<!-- screenshot: an unpaid order with the Unpaid label and the Pay with balance button -->

## What each status means

- <b>Submitted</b>: we have the order. If it also shows <b>Unpaid</b>, it waits for your payment.
- <b>In Warehouse</b>: paid and waiting to be picked.
- <b>Handling</b>: being picked.
- <b>Waiting</b>: the warehouse had to stop the order for a moment before it can go on.
- <b>Picked</b>, <b>Packing</b>, <b>Packed</b>: the parcel is being prepared.
- <b>Finalized</b>: invoiced and ready to go.
- <b>Dispatched</b>: sent. If some items could not be sent, you see <b>Modified</b> and the money for them is refunded automatically.
- <b>Cancelled</b>: the order will not be sent. The reason is shown at the top of the order.

For more about the order page, see [Reviewing your orders](/docs/reviewing-orders).

## What we send back to Shopify

- When the order is dispatched, we mark it fulfilled in Shopify with the tracking number and link. Shopify tells your customer.
- When an order is cancelled, we close the request in Shopify. On a cancelled order you can press the sync button (<b>Sync order state</b>) to send the cancellation to Shopify again. If Shopify is already up to date you see <b>The order state on Shopify is up-to-date</b>.

## An order is not in my Orders

Open the channel and press <b>Fetch orders</b>. It <b>Checks Shopify for orders that have not reached us yet</b>: it looks at recent unfulfilled orders and brings in the ones for our location. If there is nothing new you see <b>No new orders</b>. You can press it again after a couple of minutes.

If the order still does not come, check these:

- The products are connected (green handshake) in <b>My Products</b>.
- The items are stocked at the <b>aiku-</b> location in Shopify, and the location is in your shipping profile. See [The AW fulfilment location in Shopify](/docs/shopify-fulfilment-location).
- The order was not already fulfilled in Shopify, or sent to another location.

## When something goes wrong

**A cancelled order says "Fulfilment request declined: The items can't be fulfilled because you don't have the items in your portfolio."** None of the products in the order are connected in <b>My Products</b>. Add and connect them, then request fulfilment again in Shopify.

**A cancelled order says "Fulfilment request declined: Order don't have shipping information".** The order in Shopify has no delivery address. Add the address in Shopify and request fulfilment again.

**The fulfilment request was accepted in Shopify, but I cannot see the order to pay.** Open <b>Orders</b> in the channel: that is where orders from Shopify are listed. Look for the order with <b>Unpaid</b>, or press <b>Fetch orders</b>.

**My test order on Shopify did not come over.** A test order only comes to us if it contains connected products stocked at the <b>aiku-</b> location. Be careful: an order that does come to us is a real order. We pay it and send it. If you made one by mistake, ask us in the chat on our website quickly to cancel it. We can only cancel before it is dispatched.

**The order stays Submitted and Unpaid.** There was not enough balance and no card worked. Pay it as shown in <b>Pay an order that is waiting</b>, and save a card for the next ones.

**The order says "We cannot deliver to ...".** We do not send to that country from this website. The order is not paid and is not sent. Update the delivery address, or ask us in the chat on our website.

**The order is paid but has not moved for a long time.** Ask us in the chat on our website, with the order number. The order number is the <b>Reference</b> in <b>Orders</b>.

<aside class="wayfinder"><strong>Where to click</strong>
<ul>
<li><b>See your Shopify orders:</b> <b>Channels</b> → your Shopify store → <b>Orders</b>.</li>
<li><b>Pay a waiting order:</b> open the order → <b>Pay ... with balance</b>.</li>
<li><b>Bring in a missing order:</b> open the channel → <b>Fetch orders</b>.</li>
<li><b>Save a card:</b> <b>Saved Cards</b> in the menu.</li>
</ul>
</aside>
