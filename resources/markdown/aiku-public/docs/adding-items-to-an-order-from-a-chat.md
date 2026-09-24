---
title: Adding items to an order from a chat
summary: A customer in chat forgot something. Add it to the order they already placed, or raise a follow-up order that travels in the same parcel, and send a payment link for the difference - all from the conversation.
date: 2026-09-21
tags: chat, orders, payments, customer service
category: crm
---

<aside class="tldr">
A customer writes in to say they missed an item off the order they have just placed. Open the conversation's side panel and look at <b>Last orders</b>: an order the warehouse has not finished picking shows <b>+ Add items</b>, and the forgotten products go onto that same order and onto the picker's list. An order that is already picked shows <b>+ Follow-up order</b> instead, which creates a second order and tells the warehouse, on both, to send them together. Any order that still owes money shows <b>Payment link</b>, which makes a card link for exactly what is owed and copies it for you to paste into the chat.
</aside>

## Where it is

Open the conversation in <b>Chat</b>. The side panel on the right opens on <b>Overview</b>, and under the contact details is <b>Last orders</b>: the customer's five most recent orders with their state, date and total. The buttons sit on each row, next to the state, and only the ones that make sense for that order are shown.

They appear for people who can edit orders in that shop. They do not appear on marketplace orders - Faire and the like - because those orders follow what the marketplace says, and changing them on our side would only be undone. Guests have no orders, so there is nothing to show.

## Add items

Shown while the order is <b>Submitted</b>, <b>In warehouse</b>, being <b>picked</b>, or <b>waiting</b> on customer service. In other words: for as long as somebody is still going to walk the warehouse for it.

Press <b>+ Add items</b>, search for the products, set the quantity on each and press <b>Add</b>. Nothing is saved until you press it, so you can change your mind freely before that.

What happens next:

- The products go on the <b>same order</b>. No second order, no second parcel, no note to write.
- If the warehouse already holds the order, the new lines go onto the <b>picker's list</b> straight away. When picking has already started, those lines are highlighted and the picker sees a warning that the order was modified, the same one they see when a quantity is changed.
- If the order already had that product, its quantity goes up instead of a duplicate line appearing.
- The order total goes up, and an order that was paid now reads as <b>not fully paid</b>. That is correct: the customer owes the difference. See <b>Payment link</b> below.

<b>If you are told it is too late.</b> A picker can finish the order in the seconds between you opening the window and pressing <b>Add</b>. When that happens nothing is added and the message says to create a follow-up order. Reload the panel and the row will now offer exactly that.

## Follow-up order

Shown once the order is <b>Picked</b>, <b>Packing</b>, <b>Packed</b> or <b>Finalised</b> - finished in the warehouse but not dispatched. The extra items cannot join a parcel whose picking is over, so they travel as a second order in the same box.

Press <b>+ Follow-up order</b>. A new, empty order is created for the same customer and opens in a new tab. Both orders now carry the line <b>Send together with order ...</b> in the warehouse note, which is the note printed for and read by the people picking and packing. You do not write anything yourself, and the note that was already there is kept.

Then, on the new order: add the products and submit it as you would any order you place for a customer.

The new order takes the customer's usual delivery address. If the first order was going somewhere else, change the address on the new one to match - two orders to two addresses cannot share a parcel.

## Payment link

Shown on any order that still has something to pay, whatever its state, in shops that take card payments.

Press <b>Payment link</b>. A card payment link is created for <b>exactly the amount still owed</b> - the order total less what has been paid - and copied. Paste it into the conversation. The confirmation tells you the amount so you can say it to the customer.

When the customer pays, the payment appears on the order by itself and the order reads as paid. There is nothing to match up or record by hand, and no need to go to the card provider's own site to make the link.

Two things to know:

- The link is for the amount owed <b>at the moment you made it</b>. If the order changes afterwards, make a new link.
- A link stays valid for seven days.

A follow-up order appears in <b>Last orders</b>, with its <b>Payment link</b> button, once it has been submitted.

## Which one do I use?

You do not have to decide: the row only offers what that order can take. Still picking: <b>Add items</b>. Already picked: <b>Follow-up order</b>. Already dispatched: neither, because there is no parcel left to join - place a new order in the normal way.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>See the customer's orders:</b> <b>Chat</b> &rarr; open the conversation &rarr; side panel &rarr; <b>Overview</b> &rarr; <b>Last orders</b>.</li>
<li><b>Add forgotten items to the same order:</b> <b>+ Add items</b> on the order's row &rarr; choose products and quantities &rarr; <b>Add</b>.</li>
<li><b>Order already picked:</b> <b>+ Follow-up order</b> on the row &rarr; add the products on the order that opens &rarr; submit it.</li>
<li><b>Collect the difference:</b> <b>Payment link</b> on the row &rarr; paste into the conversation.</li>
<li><b>Open the order itself:</b> click its reference in <b>Last orders</b>.</li>
</ul>
</aside>
