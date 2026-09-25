---
title: Uploading products to your Wix store
summary: Add our products to your Wix channel, create them in Wix Stores or link them to products you already sell, and keep their stock up to date.
date: 2026-09-25
tags: wix, wix stores, products, upload, match, stock
category: products
series: wix
order: 2
help_routes: retina.dropshipping.customer_sales_channels.portfolios.index, retina.dropshipping.customer_sales_channels.edit
shops: awd, dssk, dse
---

<aside class="tldr">
Open your Wix channel, go to <b>My Products</b> and press <b>Add products</b>. Pick the products and press <b>Add</b>. Then either create them in Wix with <b>Upload all as new product</b> or <b>Create new product</b>, or link them to products you already have in Wix with <b>Match all with default product</b>. A product with three green ticks is live and we keep its stock updated.
</aside>

## Before you start

Your Wix channel must show three green ticks on its dashboard. If it does not, follow the guide on connecting your Wix store first.

## Add products to My Products

1. Open your Wix channel from the menu and go to <b>My Products</b>.
2. Press <b>Add products</b>. A window opens with our catalogue.
3. Search by product name or code, or browse by <b>Department</b>, <b>Sub-department</b> or <b>Family</b>.
4. Tick the products you want. The button at the top right shows how many you picked, for example <b>Add 5</b>. Press it.

<!-- screenshot: the Add products window with some products ticked and the Add button -->

The products now appear in <b>My Products</b>, but they are not in Wix yet. A yellow message says <b>You have ... products not synced yet</b>.

## Create the products in Wix

Use this when the products are not in your Wix store yet.

- To create all of them, press <b>Upload all as new product</b> in the yellow message. A window shows <b>Uploading Portfolios...</b> and counts the products. When it says <b>Uploading Complete!</b> the page reloads by itself. If it does not, refresh the page.
- To create only some, tick them in the list and press <b>Create New (N)</b>, where N is how many you ticked.
- To create one, press <b>Create new product</b> on its row.

<!-- screenshot: My Products with the yellow "products not synced yet" message and the Upload all as new product button -->

What we send to Wix:

- The product name, cut to 60 characters. Wix does not accept longer names.
- Your description, your price, the SKU and the weight.
- The product pictures.
- The stock we have, capped by <b>Max Quantity To Advertise</b> if you set it.

The price is sent as a number, without any currency conversion, in the currency of your dropshipping account. Check that your Wix site uses the same currency.

## Link products you already sell on Wix

Use this when the product is already in your Wix store and you do not want a second copy. Linking does not change your Wix product. From then on we update its stock and send you its orders.

- <b>Match all with default product</b> in the yellow message links every product whose SKU in Wix is the same as our product code. Products with no matching SKU are left alone. Linking runs in the background, so refresh the page after a moment.
- To match only some, tick them and press <b>Match (N)</b>.
- For one product, look at the <b>Wix product</b> column. If we found a Wix product that looks the same, press <b>Match with this product</b>, or <b>Choose another product from your shop</b> to pick a different one. If we found nothing, press <b>Match it with an existing product in your shop</b>. Search your Wix products, pick one and press <b>Link ... to selected item on your platform</b>.

To match by SKU, put our product code in the SKU of your Wix product first.

<!-- screenshot: a product row with the Wix product column showing Match with this product and Create new product -->

## Check that a product is live

Each row has three small ticks in <b>Status</b>: <b>Has valid platform product id</b>, <b>Exist in platform</b> and <b>Platform status</b>. Three green ticks mean the product is in your Wix store and linked. A green circle next to them means the last upload was fine.

In Wix, you find the products under <b>Catalog</b>, <b>Store Products</b>.

To link a product to a different Wix product, press <b>Change linked listing</b> on its row.

## Stock

We send stock changes to Wix regularly while <b>Stock Update</b> is on. To send them now, press <b>Update Stock</b> at the top of <b>My Products</b>.

To change stock settings, open the channel dashboard, press <b>Manage Sales Channel</b> and look under <b>Manage Stock</b>:

- <b>Stock Update</b>: turn automatic stock updates on or off.
- <b>Max Quantity To Advertise</b>: the highest stock we show in Wix, even when we have more.

A product that is discontinued or not for sale is sent to Wix with stock 0.

## Change or remove products

You cannot edit the name, description or price of a Wix product from <b>My Products</b>. Change them in Wix.

To unlink or remove products, tick them in the list first:

- <b>Unlink (N)</b> keeps the product in Wix but stops linking it to ours. We stop updating its stock and its orders stop coming to us.
- <b>Unlink & Delete (N)</b> unlinks the products and removes them from <b>My Products</b>. The products stay in your Wix store; delete them there if you no longer want them.

The bin on a row is different: it removes that product from <b>My Products</b> and also deletes it from your Wix store.

## When something goes wrong

Hold the mouse over the red message on a row to read what Wix answered.

<b>Wix shows all or most products as out of stock</b>
- Check that <b>Stock Update</b> is on in <b>Manage Sales Channel</b>.
- Check the product in <b>My Products</b>. If it is discontinued, not for sale or out of stock with us, we send 0.
- Press <b>Update Stock</b> to send stock again.
- If a product was linked with <b>Match</b>, check that the Wix product tracks inventory and has only one variant. We update one stock number per product.

<b>The product is in Wix but has no pictures</b>
Wix accepted the product but not the pictures. The row shows Wix's message. Add the pictures in Wix, or remove the product and create it again.

<b>Upload all as new product is missing</b>
The button, and <b>Add products</b>, hide for a short time after your Wix site did not answer a stock update. Refresh the page after a moment. If it stays missing, check that the channel dashboard still has three green ticks. If not, press <b>Try to reconnect</b>.

<b>The yellow message and the buttons are missing</b>
The channel is not connected. Open the channel dashboard and follow the message there.
