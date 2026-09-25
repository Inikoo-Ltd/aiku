---
title: Reviewing your orders
summary: Find the orders of each sales channel, read their status, see what was sent and what you paid, and understand why an order is unpaid, cancelled or not there at all.
date: 2026-09-25
tags: orders, order status, unpaid, cancelled, order list
category: orders
help_routes: retina.dropshipping.customer_sales_channels.orders.index, retina.dropshipping.customer_sales_channels.orders.show, retina.dropshipping.customer_sales_channels.orders.export, retina.dropshipping.customer_sales_channels.orders.review
shops: awd, dssk, dse
---

<aside class="tldr">
Orders are kept per sales channel. In the left menu open your channel and click <b>Orders</b>. Each order shows its status, and a red <b>Unpaid</b> label when we could not take the money yet. An unpaid order waits and is not sent to the warehouse until it is paid. Click the order reference to see the products, the delivery address, the tracking number and the invoice.
</aside>

## Where your orders are

Every channel you connected (Shopify, eBay, TikTok, WooCommerce and the others, and your manual channel) has its own list of orders.

1. In the left menu, find your channel under <b>Channels</b>.
2. Click <b>Orders</b> under it.

The list has these columns: <b>Status</b>, <b>Reference</b>, <b>Client</b>, <b>Date</b>, <b>Items</b> and <b>Total</b>. The newest orders are at the top. Use the search box to find an order by its reference.

The <b>Reference</b> is our order number. It is not the order number in your store and it is not a tracking number. To find the tracking number, see [Finding the tracking number of an order](/docs/tracking-numbers).

On a Manual/API channel, orders you are still preparing are not in this list. They are in <b>Baskets</b>, under the same channel, until you place them.

To download the list, use the export button at the top of the page and choose <b>Excel</b> or <b>CSV</b>.

<!-- screenshot: the Orders list of one channel, with the Status column, a reference with the red Unpaid label, and the export button -->

## What each status means

- <b>Submitted</b>: we have the order. If it is not paid, it stays here until it is paid.
- <b>In Warehouse</b>: the order is paid and waiting to be picked.
- <b>Picking</b>: the warehouse is picking the products.
- <b>Waiting</b>: picking is paused, for example while the warehouse checks a product.
- <b>Picked</b>, <b>Packing</b>, <b>Packed</b>: the parcel is being prepared.
- <b>Finalized</b>: the order is invoiced and ready to leave.
- <b>Dispatched</b>: the parcel has left our warehouse. The tracking number is on the order.
- <b>Cancelled</b>: the order will not be sent.

Next to the reference you may also see small icons for <b>Premium dispatch</b>, <b>Extra packing</b> and <b>Insurance</b> when you chose them for that order.

## Unpaid orders

A red <b>Unpaid</b> label means we could not take the full amount yet. The order stays <b>Submitted</b> and is not sent to the warehouse.

When an order comes in from your store, we pay it like this:

1. First with your balance.
2. If the balance is not enough, with your saved cards, starting with your default card.

If neither works, the order waits and we send you an email saying it is on hold. Most of the time this happens because no card is saved for the channel. To stop it happening again, save a card: see [Paying for your orders](/docs/topping-up-and-paying-with-balance).

To pay an order that is waiting:

1. Top up your balance with at least the amount due. See [Paying for your orders](/docs/topping-up-and-paying-with-balance).
2. Open the order again. A yellow box says <b>Order ... is not paid yet</b> and shows <b>Your balance</b>.
3. Click the button <b>Pay ... with balance</b>. It shows the amount due.

The order then goes to the warehouse. The button only shows when your balance covers the whole amount due, while the order is <b>Submitted</b> or <b>Picking</b>.

<b>Note:</b> we do not try your card again by ourselves. A waiting order stays waiting until you pay it.

## Inside an order

Click the order reference to open it. You see:

- At the top, a timeline with the steps the order has passed, and a <b>Paid</b> or <b>Unpaid</b> label.
- Your client: name, email, phone and delivery address.
- <b>Weight</b>: the estimated weight of all products.
- <b>Delivery Notes</b>: the parcels, their status, and under <b>Shipments</b> the courier and tracking number. The PDF icon (<b>Download Picking List</b>) downloads the list of products in the parcel.
- <b>Invoices</b>: our invoice for the order, to open or download as PDF. See [Your invoices](/docs/invoices).
- The price summary: <b>Items</b>, charges, <b>Net</b>, tax and <b>Total</b>.

These are the extra charges on this website:

{order_charges}

- The <b>Transactions</b> tab: each product with its <b>Quantity</b>. When fewer were sent than ordered, the quantity sent is shown in red above the quantity ordered, which is crossed out.
- <b>Notes from Staff</b>, <b>Delivery Instructions</b> and <b>Other Instructions</b>. Delivery instructions are printed on the shipping label.

<!-- screenshot: an order page showing the timeline, the Delivery Notes box with a tracking number and the Invoices box -->

## When not everything was sent

Sometimes we cannot send every product, for example when one runs out while we pick. The order page then shows <b>Dispatched | Modified</b>, and in the list a yellow warning icon shows next to the status. The <b>Transactions</b> tab shows which products were not sent. The money for the products we did not send goes back to your balance by itself when the order is invoiced.

## Cancelled orders

A cancelled order shows its status <b>Cancelled</b> at the top, and a red box <b>Order cancelled</b> when a reason was recorded. Money you already paid for it goes back to your balance.

For a Shopify order there is also a sync button (tooltip <b>Sync order state</b>) at the top. Click it to tell Shopify that the order was cancelled. If you see <b>The order state on Shopify is up-to-date</b>, Shopify already knows.

There is no cancel button. To cancel an order, ask us in the chat on our website with the order reference. We can only cancel it before it is dispatched. Once the order is packed it may be too late.

## Leaving a review

On some of our websites, a while after an order is dispatched, a <b>Review</b> button appears at the top of the order. Use it to rate the order and the products.

## When something goes wrong

**An order from my store is not in the list.** Check these, in this order:

- Only products that are in <b>My Products</b> of that channel come over. If none of the products in the order are in <b>My Products</b>, the order does not show in <b>Orders</b>, because there is nothing we can send.
- Check that you are looking at the right channel. Each channel has its own list.
- The channel must still be connected. If the channel page says it is not connected, reconnect it first.
- **Shopify**: only orders that Shopify sends to our fulfilment location come over, and they arrive as a fulfilment request. If a product in the order is not in <b>My Products</b>, that part of the request is declined in Shopify and the rest comes over. When the whole request is declined (none of the products are in <b>My Products</b>, or the order has no shipping address), the order shows in <b>Orders</b> as <b>Cancelled</b>, with the reason under <b>Notes from Staff</b>. Fix the order in Shopify and request fulfilment again. An order that is fulfilled by your own store, already fulfilled, or whose request was cancelled in Shopify will not come over. This is the usual reason a test order does not arrive: check in Shopify that its products are stocked at our location.

**My Shopify fulfilment request was accepted but I cannot see the order to pay.** Look in <b>Orders</b> of the Shopify channel for its status and a red <b>Unpaid</b> label. If it is not there, ask us in the chat on our website with the Shopify order number.

**The order has been Submitted for a long time.** It is almost always unpaid. Follow the steps in "Unpaid orders" above.

**The order says "We cannot deliver to ...".** We do not send to that country from this website. See [Countries we cannot deliver to](/docs/delivery-restrictions).

**A product arrived broken, or my buyer wants to return something.** Ask us in the chat on our website with the order reference and photos. Your buyer must not send anything back until it has been agreed with us in the chat. Refunds go to your balance.
