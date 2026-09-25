---
title: The AW fulfilment location in Shopify
summary: What the aiku- location in your Shopify store does, how it is added to your shipping profile for you, and what to do when products show as sold out or orders do not reach us.
date: 2026-09-25
tags: shopify, fulfilment location, shipping profile, stock, sold out
category: sales-channels
series: shopify
order: 3
help_routes: retina.dropshipping.customer_sales_channels.show
shops: awd, dssk, dse
---

<aside class="tldr">
When you install our app, we add a fulfilment location to your Shopify store. Its name starts with <b>aiku-</b>. We add it to your default shipping profile for you, so you usually do nothing. If you use more than one shipping profile, check that the <b>aiku-</b> location is in the profile of our products, or Shopify shows them as sold out and does not send us their orders.
</aside>

## What the location is for

Shopify keeps stock by location. Our products are sent from our warehouse, so we add our warehouse to your store as a fulfilment location.

- Its name is <b>aiku-</b>, then the code of our website, then the code of your channel in brackets. For example <b>aiku-awd (sho-ab12cd-3e)</b>.
- The stock of every product you connect is kept in this location. We update it for you.
- When a customer buys one of these products, Shopify sends us a fulfilment request from this location. That is how the order reaches us.

Do not delete this location and do not move our products to another location. If you do, stock stops updating and orders stop reaching us.

## Added to your shipping profile for you

Shopify only sells stock from locations that are in a shipping profile. When the app is installed, we add the <b>aiku-</b> location for you:

- to your default shipping profile, or
- if your store still has an older <b>aiku-dse</b> location from an earlier connection, to every shipping profile that older location is in.

If the location is already in one of your shipping profiles, we change nothing.

You no longer need to add the location by hand, as older guides said.

## Check it yourself

Do this if our products show as sold out in your store, or checkout shows no shipping rate for them.

1. In your Shopify admin, open <b>Settings</b>.
2. Open <b>Shipping and delivery</b>.
3. Open the shipping profile that our products are in. In most stores this is the general profile.
4. Look at the locations the profile ships from. The <b>aiku-</b> location must be there.
5. If it is not, add it to the profile and save.

<!-- screenshot: Shopify Shipping and delivery, a shipping profile with the aiku-awd location in its list of locations -->

Shopify changes its menus from time to time, so the names may be a little different in your admin.

If you made a custom shipping profile for some of our products, add the <b>aiku-</b> location to that profile too. We only add it to the default profile.

## When something goes wrong

**Our products show as sold out in Shopify.** Check the shipping profile as above. Also check that the product is connected: in <b>My Products</b> it must show a green handshake. See [Managing products on Shopify](/docs/managing-products-on-shopify).

**Upload error "No Shopify location, the AW fulfilment service is not installed on this store so stock can not be sent".** The <b>aiku-</b> location is missing. Open the channel. If you see <b>Click here to install</b>, press it and install the app in Shopify. If the channel shows <b>Reset channel</b>, use it to create the location again.

**Log message "The specified inventory item is not stocked at the location".** The product in Shopify is not stocked at the <b>aiku-</b> location, for example because it was moved to another location in Shopify. Link the product again with <b>Connect with other product</b> in <b>My Products</b>.

**Orders do not reach us.** Shopify only sends us orders for items stocked at the <b>aiku-</b> location. If the product was stocked in your own location, Shopify expects you to send it yourself. See [Your Shopify orders and their status](/docs/shopify-order-status).

<aside class="wayfinder"><strong>Where to click</strong>
<ul>
<li><b>Check the location in Shopify:</b> Shopify admin → <b>Settings</b> → <b>Shipping and delivery</b> → your shipping profile.</li>
<li><b>Check the channel:</b> <b>Channels</b> → your Shopify store → the three icons next to its name.</li>
</ul>
</aside>
