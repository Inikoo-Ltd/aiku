---
title: Why a product shows out of stock in my store
summary: Find out why your store shows a product as out of stock when it is in stock with us, and fix it on Shopify, WooCommerce, eBay, TikTok Shop and Wix.
date: 2026-09-25
tags: stock, out of stock, inventory, shopify, wix, woocommerce, ebay, tiktok, location
category: troubleshooting
help_routes: retina.dropshipping.customer_sales_channels.portfolios.index, retina.dropshipping.customer_sales_channels.edit, retina.dropshipping.back_in_stock.index
shops: awd, dssk, dse
---

<aside class="tldr">
We send stock to your store only for products that are <b>linked</b> on <b>My Products</b>, and only while your channel is connected. The usual reasons for "out of stock" are: the product is not linked, it is really out of stock or not for sale with us, your channel settings hide low stock, or (on Shopify) the stock sits at a different location. Check them in the order below, then press <b>Update Stock</b>.
</aside>

## How stock reaches your store

- We send stock only for products that are linked to a listing in your store: green in the <b>Status</b> column of <b>My Products</b>.
- When our stock changes, we update your store by ourselves. You do not need to do anything.
- We send 0 for products that are not for sale or discontinued, even if some units are left.
- Your channel settings can lower the number we send. See step 4.

## Check these, one by one

### 1. Is the channel connected?

Open <b>Channels</b> in the menu, click your channel and open <b>My Products</b>. If a red box says <b>Your channel is not connected yet to the platform</b>, we cannot send anything. Reconnect the channel first. On Shopify, make sure you pressed <b>Install</b> in Shopify to finish the connection.

### 2. Is the product linked?

Find the product on <b>My Products</b>. On Shopify the status must be the green handshake (<b>Product connected to shopify</b>). On other platforms all three ticks must be green.

If it is red, the listing in your store is not ours as far as we know, so we never update its stock. This happens often when you made the product yourself, or imported it from another app. Link it with <b>Match with this product</b>, or match all at once with <b>Match all with default product</b>. See [Using My Products](/docs/my-products).

### 3. Is it in stock with us?

Look at <b>Stocks</b> (on Shopify, <b>Stock</b>) on the row. Except on Shopify, you can use the <b>Out of stock</b> filter to list all products with no stock. A crossed-out dollar means <b>This product line is currently not for sale</b>, and a crossed-out box means it is discontinued. In all these cases your store is right to show "out of stock".

To be told when a product comes back, use the envelope button on the product on our website. Your reminders are under <b>Back In Stock Reminders</b> in the menu.

### 4. Check your channel stock settings

On the channel page press <b>Manage Sales Channel</b> (or <b>Edit</b>). Under <b>Manage Stock</b>:

- <b>Stock Update</b>: when it is off, we stop updating stock automatically. Keep it on.
- <b>Stock Threshold</b>: when our stock falls to this number or below, we send 0. For example, with a threshold of 10, a product with 8 units shows out of stock. Leave it empty to send the real stock.
- <b>Max Quantity To Advertise</b>: the most we show, even if we have more. Leave it empty for no cap.

<!-- screenshot: Manage Stock section of the channel settings with Stock Update, Max Quantity To Advertise and Stock Threshold -->

### 5. Push the stock now

On <b>My Products</b>, press <b>Update Stock</b> (Shopify, WooCommerce, eBay, TikTok Shop and Wix). You see <b>Stock update started</b>. It can take a few minutes. Then open the <b>Logs</b> tab: rows of type <b>Update Stock</b> show <b>Done</b> or <b>Failed</b> with the answer of your platform.

If it says <b>Nothing to update</b>, none of your products is linked yet. Go back to step 2.

## Shopify

On Shopify our stock lives at our own fulfilment location, named <b>aiku-</b> followed by our shop code and your channel code in brackets, for example <b>aiku-awd (my-store)</b>.

1. In Shopify, open <b>Products</b> and the product that shows out of stock.
2. In the <b>Inventory</b> section, check that our location is listed and has stock.
3. If the stock is at another location (for example your own shop address) with 0, that is the number Shopify shows for that location. Our stock is only ever at our location.

If Shopify answers that the product is not stocked at our location, we add it to our location by ourselves, so the next stock update can go through. If the <b>Logs</b> tab says <b>No variant on Shopify matches this sku</b>, the SKU of the Shopify variant is not our product code. Change the SKU in Shopify to our code, or link the product again with <b>Connect with other product</b>.

## Wix

We never send stock for a Wix product that is not linked. If Wix says all your products are out of stock, the products were most likely added in Wix directly or were not matched. On <b>My Products</b>, use <b>Match all with default product</b> to link them by SKU, or <b>Create new product</b> to let us create them. Then press <b>Update Stock</b>.

## eBay

When we send 0, eBay shows the listing as out of stock. If your eBay account does not use eBay's out-of-stock option, eBay may end the listing instead. Turn the option on in your eBay selling preferences so listings stay and come back when we have stock.

## WooCommerce and TikTok Shop

Check the <b>Logs</b> tab. On WooCommerce, a <b>Failed</b> stock update with "503", "timed out" or "The store answered with a web page instead of data" means your website did not let us in. Check your site is online and that your hosting or security plugin does not block us, then press <b>Update Stock</b> again.

## When something goes wrong

- **"Stock update failed. This channel is not connected to the platform, so stock cannot be updated."** Reconnect the channel, then try again.
- **"Nothing to update".** None of your products is linked. Link them first (step 2).
- **The stock is right on My Products but wrong in my store, and Logs show Done.** Your store may add stock from its own locations or apps. Check that no other app or location changes the stock of that product.
- **The product came back in stock but my store still shows 0.** Press <b>Update Stock</b> and check the <b>Logs</b> tab. If the update shows <b>Failed</b>, the message there says why.
