---
title: Managing products on Shopify
summary: Add our products to your Shopify channel, create them in your store or link them by SKU to products you already sell, keep stock up to date, unlink or remove them, and fix upload errors.
date: 2026-09-25
tags: shopify, products, portfolio, match, sku, upload, stock
category: products
series: shopify
order: 2
help_routes: retina.dropshipping.customer_sales_channels.portfolios.index, retina.dropshipping.customer_sales_channels.show, retina.dropshipping.customer_sales_channels.edit
shops: awd, dssk, dse
---

<aside class="tldr">
Open your Shopify channel and go to <b>My Products</b>. Press <b>Add products</b>, tick the products you want and add them. Then, for each product, either press <b>Create new product</b> to make it in Shopify, or link it to a product you already sell with <b>Match with this product</b>. A green handshake means the product is connected: we keep its stock up to date and send you its orders.
</aside>

## Before you start

Your Shopify channel must be connected, with the app installed. See [Connecting your Shopify store](/docs/connecting-shopify). Until then, <b>My Products</b> shows <b>Click here to install</b> instead of your products.

## Add products to your channel

1. Open <b>Channels</b> and click your Shopify store, or pick it in the left menu.
2. Open <b>My Products</b>. You can also click <b>View all</b> on the <b>Products</b> box of the channel dashboard.
3. Press <b>Add products</b>. A window opens: <b>Select products to be added to shop</b>.
4. Type in the search box. Use the <b>Product</b>, <b>Department</b>, <b>Sub-department</b> or <b>Family</b> buttons under it to choose what the list shows.
5. Tick the products you want and press <b>Add</b>. The button shows how many you ticked.
6. The window closes and the products are added to your list.

<!-- screenshot: the Select products to be added to shop window with a few products ticked -->

The products are now in <b>My Products</b>. They are not in Shopify yet: they show a red handshake, <b>Not connected</b>.

## Connect each product: create or match

Every product must be connected to a product in your Shopify store. In the list you have two ways.

**Create new product.** We make a new product in Shopify with our name, description, pictures, SKU, barcode, weight and price. Use it for products you do not sell yet. See [What happens to your product descriptions](/docs/product-descriptions-after-connecting).

**Match.** Use it when you already sell this product in Shopify, so you do not get a duplicate. We look for a product in your store with the same SKU or barcode.

- If we found one, you see it with its picture and name. Press <b>Match with this product</b>.
- If it is not the right one, press <b>Choose another product from your shop</b>.
- If we found nothing, press <b>Match it with an existing product in your shop</b>. A window lists the products of your store. Search in it, pick the product and press <b>Link ... to selected item on your platform</b>.

When it worked, the handshake turns green: <b>Product connected to shopify</b>. You see the name and picture of the Shopify product next to it.

<!-- screenshot: a product row with the suggested match, the Match with this product button and the Create new product button -->

To change the link later, press <b>Connect with other product</b>.

## Many products at once

When some products are not connected, you see <b>You have ... products not synced yet</b> above the list, with two buttons:

- <b>Upload all as new product</b>: creates all of them in Shopify. A window shows the progress.
- <b>Match all with default product</b>: links every product whose SKU is already in your Shopify store. Products whose SKU is not in your store are left as they are.

You can also tick products in the list. Buttons appear above it:

- <b>Create New (...)</b>: creates the ticked products in Shopify. It shows when at least one ticked product is not connected yet.
- <b>Match (...)</b>: links the ticked products to your Shopify products with the same SKU.
- <b>Edit Price (...)</b>: changes the price. Read the warning in [What happens to your product descriptions](/docs/product-descriptions-after-connecting) first.
- <b>Unlink (...)</b> and <b>Unlink & Delete (...)</b>: see below.

<b>Tip:</b> start with a few products. Check them in Shopify, then do the rest.

## Match by SKU instead of creating new products

If you already sell our products in Shopify, give each Shopify product (or variant) the same SKU as our product code before you match. Then use <b>Match all with default product</b>. This links your existing listings and does not create new ones.

If you sell several of our products as the variants of one Shopify product, for example one product with a variant per colour, each variant must have its own SKU. Ask us in the chat on our website to turn on linking to existing variants for your channel. You cannot turn it on yourself. Then matching links each product to the variant with its SKU, and we never change the price, SKU or barcode of your variants.

## Find products in the list

Press <b>Filter</b> above the list to show only products that are <b>Only For Sale</b>, <b>Not For Sale</b>, <b>Discontinued</b>, <b>Connected to Shopify</b> or <b>Not Connected</b>.

Each row shows our stock, weight, size, price and RRP. A red sign in the row means the product is not for sale right now, or is discontinued. Products that are not for sale cannot be created or matched. Remove discontinued products from your channel and from your Shopify store.

## Stock, weights and sizes

- Stock: while <b>Stock Update</b> is on, we send the stock of every connected product to the <b>aiku-</b> location in Shopify. To change it, open the channel and press <b>Manage Sales Channel</b>. There you can also set <b>Max Quantity To Advertise</b> and <b>Stock Threshold</b>.
- Weights and sizes: press <b>Update all dimensions</b> above the list to send the weight and size of all your products to Shopify again.

## Unlink or remove a product

- <b>Unlink</b> (the broken chain icon on a connected product): the product stays in your list but is no longer connected. Its handshake turns red. We stop updating its stock in Shopify, so the last stock we sent stays there.
- <b>Remove product</b> (the skull icon on a product that is not connected): removes it from <b>My Products</b>.
- <b>Unlink & Delete (...)</b>: does both for the ticked products.

The product itself stays in your Shopify store. If you do not want to sell it any more, archive or delete it in Shopify yourself.

## When something goes wrong

To see what happened with each upload, open the <b>Logs</b> tab in <b>My Products</b>: the clock icon on the right, next to the <b>My Products</b> tab.

**The handshake stays red after "Create new product".** Open the <b>Logs</b> tab and read the message. The most common ones are below.

**A product with the same SKU already exists in my store.** Do not create it again. Use <b>Match with this product</b> or <b>Match it with an existing product in your shop</b> instead. Or change the SKU of the product in Shopify.

**"No Shopify location, the AW fulfilment service is not installed on this store so stock can not be sent".** The app is not fully installed. Open the channel and use <b>Click here to install</b>. See [The AW fulfilment location in Shopify](/docs/shopify-fulfilment-location).

**"No variant on Shopify matches this sku".** The product is linked, but we cannot find its SKU in Shopify any more. The SKU was changed, or the product or variant was deleted in Shopify. Put the SKU back, or link the product again with <b>Connect with other product</b>.

**"You need to add option values for Colour"** (or another option). You matched with a Shopify product that has options, such as colours. Give each variant its own SKU and ask us in the chat on our website to turn on linking to existing variants, or match with a product without options.

**"None of the variants of this Shopify product has the sku ...".** Linking to variants is on, but no variant carries our SKU. Set the SKU on the variant you want and match again.

**"More than one variant of this Shopify product has the sku ...".** Two variants have the same SKU. Give each variant its own SKU.

**"Throttled".** Shopify asked us to slow down because many changes were sent at once. Wait a few minutes and try again.

**"Error in API response: HTTP 502" or "504".** Shopify did not answer in time. This is on Shopify's side. Try again later.

**"Could not check whether this product is already in Shopify, nothing was created to avoid a duplicate".** We could not reach your store at that moment, so we did not create anything. Try again later.

**Shopify descriptions do not change when ours change.** This is normal. See [What happens to your product descriptions](/docs/product-descriptions-after-connecting).

<aside class="wayfinder"><strong>Where to click</strong>
<ul>
<li><b>Add products:</b> your channel → <b>My Products</b> → <b>Add products</b>.</li>
<li><b>Create in Shopify:</b> <b>My Products</b> → <b>Create new product</b>, or tick products → <b>Create New (...)</b>.</li>
<li><b>Link to a product you already sell:</b> <b>My Products</b> → <b>Match with this product</b>, or <b>Match all with default product</b>.</li>
<li><b>See upload errors:</b> <b>My Products</b> → <b>Logs</b>.</li>
<li><b>Stock settings:</b> your channel → <b>Manage Sales Channel</b>.</li>
</ul>
</aside>
