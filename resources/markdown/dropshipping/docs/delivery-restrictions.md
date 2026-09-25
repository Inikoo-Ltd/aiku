---
title: Countries we cannot deliver to
summary: What the message "We cannot deliver to" means on a basket or an order, and how to fix Shopify checkouts that refuse to ship our products to a country.
date: 2026-09-25
tags: delivery, countries, shipping, shopify, shipping profile, forbidden address
category: orders
help_routes: retina.dropshipping.customer_sales_channels.orders.show, retina.dropshipping.customer_sales_channels.basket.show
shops: awd, dssk, dse
---

<aside class="tldr">
Each of our websites has a list of countries it does not deliver to. When the delivery address of an order is in one of them, you see <b>We cannot deliver to ...</b>, you cannot pay, and the order does not go to the warehouse. Change the address while the order is still a basket, or ask us in the chat on our website. A different problem is a Shopify checkout that will not ship our products to a country: that is a shipping setting in your Shopify store.
</aside>

## "We cannot deliver to ..." on your basket or order

If the delivery address is in a country the website does not deliver to, you see this in red:

<b>We cannot deliver to (country). Please update the address or contact support.</b>

What happens then:

- In a basket, the <b>Continue to Checkout</b> and <b>Place order</b> buttons are hidden.
- An order that comes in from your store is not paid and stays <b>Submitted</b>. It is not sent to the warehouse.
- On the order page, the yellow box asking you to top up and the <b>Pay ... with balance</b> button are hidden, because the order cannot be sent. The order still shows as <b>Unpaid</b>.

What to do:

- **Basket (Manual/API channel)**: click <b>Edit</b> next to the delivery address and change it, if the address was wrong.
- **Order from your store**: you cannot change the address on the order. Ask us in the chat on our website with the order reference.

Some countries are blocked only for part of the country, by postcode. The same message is shown.

The list is different for each website. This website does not deliver to these countries:

{blocked_delivery_countries}

## "Your current billing address is marked as forbidden"

This message is about your own billing address, not your buyer's. Update the address in your account, or ask us in the chat on our website.

## Shopify: "unable to deliver" at your store's checkout

This happens in your Shopify store, before the order reaches us. Shopify blocks the checkout when it has no shipping rate from the location of the product to the buyer's country. Products you made yourself may still work, because they use a different location.

Our products are stocked in Shopify at our fulfilment location. Its name is <b>aiku-</b> followed by the website code, then your channel's code in brackets, for example <b>aiku-awd (my-store)</b>. See [The AW fulfilment location in Shopify](/docs/shopify-fulfilment-location). Check these settings in your Shopify admin:

1. **Locations** (Settings → Locations): our location must be active. Remove old or duplicate dropshipping locations you no longer use.
2. **Shipping profile** (Settings → Shipping and delivery): open the profile that holds our products and check that our location is in it.
3. **Zones and rates**: in that profile, the buyer's country must be in a shipping zone, and the zone needs at least one rate (paid or free).
4. **Product**: open the product that fails and check which shipping profile it uses. Move it to the profile from step 2 if needed.

<!-- screenshot: Shopify shipping profile with the aiku- location and a zone that contains the buyer's country -->

If all four are correct and the checkout still fails, contact Shopify support. Shipping zones and rates are set in your store, so we cannot change them for you.

Even when Shopify allows the checkout, we can only send the order if the country is not on our list above.

## When something goes wrong

**My order has been Submitted for days and there is no pay button.** Open the order. If you see <b>We cannot deliver to ...</b>, the country is blocked. Ask us in the chat on our website with the order reference.

**The buyer gave a wrong country by mistake.** Ask us in the chat on our website with the order reference and the right address.
