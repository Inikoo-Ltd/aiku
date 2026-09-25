---
title: Placing orders manually
summary: Create an order for a client on a Manual/API channel, add products, choose delivery options, pay at checkout and follow the order until it is dispatched.
date: 2026-09-25
tags: manual, orders, basket, checkout, payment, balance, collection, dispatch
category: orders
series: manual
order: 4
help_routes: retina.dropshipping.customer_sales_channels.client.show, retina.dropshipping.customer_sales_channels.basket.index, retina.dropshipping.customer_sales_channels.basket.show, retina.dropshipping.checkout.show, retina.dropshipping.customer_sales_channels.orders.index, retina.dropshipping.customer_sales_channels.orders.show, retina.dropshipping.order_upload_templates
shops: awd, dssk, dse
---

<aside class="tldr">
Open the client in your Manual/API channel and press <b>Create Order</b>. A basket opens: add products with <b>Add products</b>, choose the delivery options and press <b>Continue to Checkout</b>. We use your account balance first and you pay the rest by card. When the order is paid it goes to the warehouse and you follow it under <b>Orders</b>.
</aside>

## Before you start

- You need a Manual/API channel. See [The Manual/API channel](/docs/manual-and-api-channel).
- The person you ship to must be a client of that channel. See [Managing clients](/docs/managing-clients).

## Create the order

1. Open your Manual/API channel and then <b>Clients</b>.
2. Click the client's name. The client page opens.
3. Press <b>Create Order</b>.

The basket for the new order opens. At the top it shows the client, their contact details and the delivery address. Check the name and address before you go on.

## Add products

- <b>Add products</b> opens a window <b>Add products to Order</b>. Search by product name or code, type the quantity and add the products. You can pick any product we sell, not only the ones in <b>My Products</b>.
- <b>Upload products</b> adds many products from a spreadsheet. Download the template (.xlsx) in the window, fill in the columns <b>code</b> and <b>quantity</b>, and upload it.

The products appear in the list. You can change quantities there, or remove a line. The estimated weight of the parcel is shown next to the address.

<!-- screenshot: a basket with the client and address at the top, products in the list, the delivery options and the Continue to Checkout button -->

## Choose delivery options

- <b>Collection</b>: switch it on if you, or a courier you book, will collect the order from our warehouse instead of us sending it. It has an extra charge. When it is off, we send the order to the address shown. Press <b>Edit</b> under the address to change it for this order.
- Faster dispatch: on AW Dropship UK the option is called <b>Same Day Dispatch</b>, on AW Dropship Europe <b>Premium Dispatch</b> and on AW Dropship España <b>Envío Premium</b>. It has an extra charge. Read the information icon next to it for the conditions.
- <b>Extra protective packing for fragile items</b> (AW Dropship UK only): extra packing for breakable products. It has an extra charge.
- <b>Delivery Instructions</b>: a note for the courier. <b>This message will be printed in shipping label</b>, so write it for the courier, not for us.
- <b>Other Instructions</b>: a note for our team.

The charges and the order total update when you switch an option.

These are the extra charges on this website:

{order_charges}

## Pay

If your account balance covers the whole order, the basket shows <b>Place order</b> instead of <b>Continue to Checkout</b>. Press it and the order is paid from your balance. The note says <b>This is your final confirmation. You can pay totally with your current balance.</b>

Otherwise:

1. Press <b>Continue to Checkout</b>.
2. The checkout shows the order number. If you have some balance, it tells you how much is paid with balance, and asks you to pay the rest.
3. In <b>Online payments</b>, enter your card details and confirm. Your bank may ask you to approve the payment in its app or with a code.
4. When the payment is done, the page says <b>Payment done. Waiting for confirmation...</b> and then opens the order.

Press <b>Back to basket</b> on the checkout page to change the order before you pay.

## Unfinished orders: Baskets

An order you created but did not pay stays in <b>Baskets</b> under your channel. The number next to <b>Baskets</b> in the menu shows how many you have. Open one to finish it, or press <b>Delete</b> on its row (tooltip <b>Delete basket</b>) to remove it. A basket is not sent to the warehouse until it is paid.

## Follow your orders

Open <b>Orders</b> under your channel. The list shows the <b>Status</b>, <b>Reference</b>, client, <b>Date</b>, items and total. Click an order to see its products, delivery notes, shipments with tracking links and invoices.

The status icon tells you where the order is. Hover it to see the name:

- <b>Submitted</b>: we have received the order.
- <b>In Warehouse</b>, <b>Picking</b>, <b>Picked</b>, <b>Packing</b>, <b>Packed</b>: our team is preparing it.
- <b>Waiting</b>: it is on hold in the warehouse.
- <b>Finalized</b>: ready to leave.
- <b>Dispatched</b>: sent. The tracking link is on the order.
- <b>Cancelled</b>: the order was cancelled.

Icons at the top of an order show the options you chose: a star for <b>Premium dispatch</b>, a box for <b>Extra packing</b>.

If we cannot send some items, the order shows that <b>Some items are not being sent</b>. The money for those items is refunded automatically.

The order page lists its invoices with a download button. All your invoices are also under <b>Invoices</b> in the menu.

## When something goes wrong

- <b>I cannot see Create Order on the client page.</b> The button is only on clients of a Manual/API channel. On connected channels, orders come in from your store.
- <b>The basket says "We cannot deliver to …".</b> We do not ship to that country. Change the delivery address, or switch on <b>Collection</b> if you arrange the transport yourself.
- <b>The basket says your billing address is marked as forbidden.</b> Update the billing address in your account, or ask us in the chat on our website.
- <b>Continue to Checkout is greyed out and asks me to upload a file.</b> You chose a printed insert that needs your artwork. Upload the file for it, or remove the insert, before you check out.
- <b>The card payment failed.</b> The checkout says <b>Something went wrong</b>. Check the card details and that your bank approved the payment, then try again. You can also top up your balance and pay with it.
- <b>The checkout says "Payment still processing".</b> Do not pay again. The order is submitted automatically once the payment is confirmed.
- <b>The checkout says "Order already submitted".</b> The order is paid already. Open it under <b>Orders</b>.
- <b>My order shows Unpaid.</b> The payment did not cover the order. Add money to your balance with <b>Top Up</b>, open the order and press <b>Pay … with balance</b>. The button shows when your balance covers the amount due. The order then goes to the warehouse.
- <b>I need to change or cancel an order I already paid.</b> There is no cancel button. Ask us in the chat on our website as soon as possible. We can only cancel or change it before it is dispatched. Once it is packed it may be too late.
