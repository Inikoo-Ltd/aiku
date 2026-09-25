---
title: Payment cards and options
summary: How you pay for orders, which payment methods the checkout offers, and how to save a card so orders from your connected stores are paid automatically.
date: 2026-09-25
tags: payment, card, saved cards, checkout, paypal, apple pay, google pay, automatic payment
category: payments
help_routes: retina.dropshipping.checkout.show, retina.dropshipping.mit_saved_cards.dashboard, retina.dropshipping.mit_saved_cards.create
shops: awd, dssk, dse
---

<aside class="tldr">
Your balance is always used first. What the balance does not cover you pay at the checkout under <b>Online payments</b>: card, Apple Pay, Google Pay, PayPal and other methods, depending on your country and device. Orders that come in from your connected stores are paid without you: from your balance first, then from a card you saved under <b>Saved Cards</b>. Save a card, or keep your balance topped up, so these orders are not left unpaid.
</aside>

## Two ways orders get paid

- <b>Orders you create yourself</b> (manual orders): you pay at the checkout while you watch.
- <b>Orders from your connected stores</b> (Shopify, WooCommerce, eBay, TikTok and the others, or through the API): nobody is at the checkout, so we take the money ourselves. We use your balance first, then your saved cards. See [Topping up and paying with balance](/docs/topping-up-and-paying-with-balance).

## Paying at the checkout

1. On the <b>Dashboard</b>, under <b>Quick links (Shortcuts)</b>, press <b>Create manual Order</b>.
2. Under <b>Select Customer Client</b>, choose the person you are sending to, or press <b>Create new client here</b>. Press <b>Create Order</b>.
3. Add products to the basket, check the address, and press <b>Continue to Checkout</b>. If your balance covers the whole order, the basket shows <b>Place order</b> instead: press it and the order is paid from your balance.
4. The checkout shows your <b>Order number</b> and the summary. If you have money in your balance, it is used first: you see how much will be paid with balance and <b>Please paid the rest with your preferred method below:</b>.
5. Under <b>Online payments</b>, choose how to pay the rest and follow the steps. Your bank may ask you to confirm the payment in its app or with a code.
6. After you pay, you see <b>Payment done. Waiting for confirmation...</b>. When the payment is confirmed, the order is sent to our warehouse.

If your balance covers the whole order, there is no payment form: you only see <b>Place order</b>.

<!-- screenshot: the checkout page with the order summary and the Online payments form showing card, Apple Pay and PayPal -->

## Which payment methods you can use

The <b>Online payments</b> form shows the methods that work for your country, currency and device. Customers use:

- Debit and credit cards
- Apple Pay (on Apple devices) and Google Pay
- PayPal
- Klarna
- In some European countries: iDEAL, Przelewy24 and Bancontact

If you do not see a method you expect, it is not available for your country, currency or device. Bank transfer and cash on delivery are not offered at the dropshipping checkout.

## Saving a card for automatic payments

Saved cards are used to pay orders from your connected stores when your balance is not enough.

The <b>Saved Cards</b> item appears in the left menu once you have connected a store, created an API token or saved a card. A small dot on it means you have no saved card yet.

To save a card:

1. Press <b>Saved Cards</b> in the left menu. The page is called <b>Credit Card Dashboard</b>.
2. Press <b>Save Credit Card</b> at the top (or <b>Add credit card</b> above your list of cards).
3. Enter your card details. Your bank will ask you to confirm. This is needed so we can charge the card later without you.
4. The card appears in the list, which shows its <b>Card type</b>, <b>Expired</b> status, <b>Last 4 digits</b> and <b>Added date</b>.

Only cards can be saved here. Apple Pay, Google Pay and PayPal cannot be saved for automatic payments.

<!-- screenshot: the Credit Card Dashboard with one saved card marked as default and the Set as default and Unlink buttons -->

## More than one card

- The default card has a green tick. Press <b>Set as default</b> on another card to use it first.
- When we need to pay an order, we try the default card first, then your other cards, one by one, until one works.
- To remove a card, press <b>Unlink</b> and confirm.

Check the expiry date of your cards. When a card expires, save the new one and unlink the old one.

## When something goes wrong

- <b>Something went wrong</b> / <b>Failed to communicate with the payment service.</b>: the payment did not start. Refresh the checkout page and try again, or choose another method.
- <b>Payment still processing</b> / <b>Your order will be submitted automatically once the payment is confirmed.</b>: your bank has not confirmed yet. Do not pay again. Check the order in a few minutes.
- <b>Order already submitted</b> / <b>This order has already been submitted and cannot be paid again.</b>: the order is already paid. You are taken to the order page.
- <b>Online payments are temporarily unavailable</b>: the payment service is not answering. Try again later, or top up your balance and pay with it.
- <b>Insert file missing</b>: an insert in your order has no file. Go back to the basket and upload the file before checkout.
- <b>We cannot deliver to …</b> or <b>Your current billing address (…) is marked as forbidden</b>: we cannot take payment for this address. Change the address, or ask us in the chat on our website.
- An order from your store shows <b>Unpaid</b> and you got an email that it is on hold: your balance was not enough and no saved card worked. Top up your balance and press <b>Pay … with balance</b> on the order. See [Topping up and paying with balance](/docs/topping-up-and-paying-with-balance).
- Your card was declined for an automatic payment: your bank refused the charge. Check it in <b>Saved Cards</b> — it may be refused or expired — then top up your balance and pay with it, or save another card and set it as default.
