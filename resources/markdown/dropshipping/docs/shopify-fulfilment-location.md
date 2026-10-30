---
title: The AW fulfilment location in Shopify
summary: What the aiku- location and the AW Connect shipping profile in your Shopify store do, which shipping rates your customers see for our products, and what to do when products show as sold out or orders do not reach us.
date: 2026-10-30
tags: shopify, fulfilment location, shipping profile, shipping rates, markets, stock, sold out, returns, order routing
category: sales-channels
series: shopify
order: 3
help_routes: retina.dropshipping.customer_sales_channels.show
shops: awd, dssk, dse
---

<aside class="tldr">
When you install our app, we add a fulfilment location to your Shopify store, named <b>aiku-</b>, and a shipping profile, <b>AW Connect shipping</b>, with our products and our shipping prices. At checkout your customers see <b>AW Standard</b> shipping for our products. You usually do nothing.
</aside>

## What the location is for

Shopify keeps stock by location. Our products are sent from our warehouse, so we add our warehouse to your store as a fulfilment location.

- Its name is <b>aiku-</b>, then the code of our website, then the code of your channel in brackets. For example <b>aiku-awd (sho-ab12cd-3e)</b>.
- The stock of every product you connect is kept in this location. We update it for you.
- When a customer buys one of these products, Shopify sends us a fulfilment request from this location. That is how the order reaches us.

Do not delete this location and do not move our products to another location. If you do, stock stops updating and orders stop reaching us.

## Shipping set up for you

When the app is installed we add a shipping profile to your store. Its name is <b>AW Connect shipping</b>, then the code of your channel in brackets, for example <b>AW Connect shipping (sho-ab12cd-3e)</b>. It works the same whether your store uses shipping profiles or shipping by market.

- It ships from the <b>aiku-</b> location only.
- It holds every product you connect from us. We add new products and take out removed ones for you, usually within ten minutes.
- Its rates are our shipping prices, by country and parcel weight, in your store's currency. At checkout they show as <b>AW Standard</b>. When we change our prices, the profile follows within a day.
- Where we price shipping by hand (very heavy parcels, countries outside our zones), the profile uses our highest price for that zone, so your customers can always check out. What we charge you for the order does not change.
- Shopify does not let you edit these rates. You can turn them off, but then your customers see no shipping for our products, unless one of your own rates ships from the <b>aiku-</b> location.

Your other products keep your own shipping settings and rates. We do not change them.

## Stores on shipping by market

Shopify is moving every store from shipping profiles to shipping by market. Such a store shows <b>Shipping has moved to Markets</b> in <b>Settings</b>, <b>Shipping and delivery</b>. Our profile shows in each market as a service of our app, next to your own rates.

If one of your market rates ships from the <b>aiku-</b> location, for example because you added it by hand as an older version of this guide said, your customers see that rate as well as <b>AW Standard</b>. To show only ours, take the <b>aiku-</b> location out of that rate:

1. In your Shopify admin, open <b>Markets</b> and open a market you sell to.
2. In <b>Shipping</b>, next to a rate, press <b>...</b> and choose <b>Edit</b>.
3. In <b>Ships from</b>, remove the <b>aiku-</b> location and save.

Our products are only sold to the countries your markets include. A customer in a country none of your markets covers sees them as sold out.

## Your own stock of our products

Products in our shipping profile ship only from the <b>aiku-</b> location, so Shopify does not send them from your own locations. If you hold some of our products yourself, for example an unused item a customer returned to you, list them in your store as a separate product and send those orders yourself.

## Check it yourself

Do this if our products show as sold out in your store, or checkout shows no shipping rate for them.

1. In your Shopify admin, open <b>Settings</b>.
2. Open <b>Shipping and delivery</b>.
3. Find <b>AW Connect shipping</b>, under our app. It must ship from the <b>aiku-</b> location and list our products.
4. If it is missing, or a product is not in it, wait a day: we check every store each night. If it does not come back, contact us.

<!-- screenshot: Shopify Shipping and delivery, the AW Connect shipping profile under the app, with the aiku-awd location -->

Shopify changes its menus from time to time, so the names may be a little different in your admin.

## When something goes wrong

**Our products show as sold out in Shopify.** Check the <b>AW Connect shipping</b> profile as above, and that the customer's country is in one of your markets. Also check that the product is connected: in <b>My Products</b> it must show a green handshake. See [Managing products on Shopify](/docs/managing-products-on-shopify).

**Upload error "No Shopify location, the AW fulfilment service is not installed on this store so stock can not be sent".** The <b>aiku-</b> location is missing. Open the channel. If you see <b>Click here to install</b>, press it and install the app in Shopify. If the channel shows <b>Reset channel</b>, use it to create the location again.

**Log message "The specified inventory item is not stocked at the location".** The product in Shopify is not stocked at the <b>aiku-</b> location, for example because it was moved to another location in Shopify. Link the product again with <b>Connect with other product</b> in <b>My Products</b>.

**Orders do not reach us.** Shopify only sends us orders for items stocked at the <b>aiku-</b> location. If the product was stocked in your own location, Shopify expects you to send it yourself. See [Your Shopify orders and their status](/docs/shopify-order-status).

<aside class="wayfinder"><strong>Where to click</strong>
<ul>
<li><b>Check our shipping profile:</b> Shopify admin → <b>Settings</b> → <b>Shipping and delivery</b> → <b>AW Connect shipping</b>.</li>
<li><b>Shipping by market:</b> Shopify admin → <b>Markets</b> → your market → <b>Shipping</b>.</li>
<li><b>Check the channel:</b> <b>Channels</b> → your Shopify store → the three icons next to its name.</li>
</ul>
</aside>
