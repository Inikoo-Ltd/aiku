---
title: Using My Products
summary: Read the My Products list of a channel, send products to your store or link them to listings you already have, keep stock up to date and read the upload errors.
date: 2026-09-25
tags: products, my products, portfolio, upload, match, sku, stock, logs
category: products
help_routes: retina.dropshipping.customer_sales_channels.portfolios.index, retina.dropshipping.customer_sales_channels.portfolios.show
shops: awd, dssk, dse
---

<aside class="tldr">
<b>My Products</b> is the list of our products you sell in one channel. Each channel has its own list. From here you send each product to your store with <b>Create new product</b>, or link it to a listing you already have with <b>Match</b>. When the product is linked we keep its stock up to date, and the <b>Logs</b> tab shows every upload, match and stock update with the answer of your platform.
</aside>

## Open My Products

1. Open <b>Channels</b> in the menu. Each channel shows under it with its logo.
2. Click the channel, then <b>My Products</b>. The number next to it is how many products are in the list.

The page has three tabs: <b>My Products</b>, <b>My Bundles</b> (see [Creating bundles](/docs/bundles)) and <b>Logs</b> (the clock icon on the right).

If a red box says <b>Your channel is not connected yet to the platform</b>, the connection to your store is broken. Nothing can be uploaded and no stock is sent until you reconnect. Follow the connection guide for your platform.

<!-- screenshot: My Products page of a Shopify channel with the tabs, the buttons at the top and a few rows -->

## What each row shows

- **Product**: our product code (click it to open the product), the name, <b>Stocks</b>, <b>Weight</b> (product weight / weight with packing), <b>Dimension</b>, our <b>Price</b> (what you pay us) and the <b>RRP</b>. If your channel shows prices with VAT, you see <b>Price (include VAT)</b> and <b>RRP (include VAT)</b> (not on Shopify).
- **Status**: on Shopify, a green handshake means <b>Product connected to shopify</b> and a red one means <b>Not connected</b>. On other platforms there are three ticks: <b>Has valid platform product id</b>, <b>Exist in platform</b> and <b>Platform status</b>. Three green ticks mean the product is live and linked.
- **Message**: a green tick when everything is fine. A red message when your platform refused the product (on Shopify, look in the <b>Logs</b> tab instead). Click it to see <b>Answer of ...</b> with the full text from your platform and, often, what to do. A crossed-out box means <b>This product line has been discontinued. Please remove this item</b>. A crossed-out dollar means <b>This product line is currently not for sale</b>.
- **Your platform's product column** (for example <b>Shopify product</b> or <b>eBay product</b>): which listing in your store this product is linked to, or the buttons to link it.

## Send a product to your store

For a product that is not linked yet you have two choices.

**Create a new listing.** Press <b>Create new product</b>. We create the product in your store with our name, description, images, price, SKU and stock.

**Link to a listing you already have.** Use this when you already sell the product and do not want a second copy.
- If we found a listing in your store with the same SKU, it shows in the row. Press <b>Match with this product</b>.
- To pick a different one, press <b>Choose another product from your shop</b>, or <b>Match it with an existing product in your shop</b> when we found nothing. Search your store, pick the item and press <b>Link ... to selected item on your platform</b>.
- To change a product that is already linked, press <b>Change linked listing</b> (on Shopify: <b>Connect with other product</b>).

## Do many products at once

When some products are not linked yet, a yellow bar says <b>You have ... products not synced yet</b>. It has two buttons:

- <b>Upload all as new product</b>: creates all of them in your store. Not shown on eBay.
- <b>Match all with default product</b>: links each product to the listing in your store with the same SKU. We compare the SKU in your store with the SKU of the product in <b>My Products</b> and with our product code, and upper or lower case does not matter. Products with no listing of that SKU are left as they are.

To work on some products only, tick them in the list. These buttons appear:

- <b>Create New (...)</b>: creates the ticked products in your store.
- <b>Match (...)</b>: links the ticked products by SKU.
- <b>Unlink (...)</b> and <b>Unlink & Delete (...)</b>: see [Removing products](/docs/removing-products).
- <b>Edit Price (...)</b>: sets your selling price for the ticked products on eBay, Shopify, WooCommerce and Wix, as a percentage or amount above or below the RRP. Not shown when your channel is set to keep its own prices.

Big jobs run in the background. A progress window shows how many are done, and the page reloads by itself.

<!-- screenshot: the yellow "products not synced yet" bar with Upload all as new product and Match all with default product -->

## Find products in the list

Use the search box, or the filter buttons: <b>Only For Sale</b>, <b>Not For Sale</b>, <b>Discontinued</b> and <b>Out of stock</b>. On Shopify the filters are in the <b>Filter</b> menu: <b>Only For Sale</b>, <b>Not For Sale</b>, <b>Discontinued</b>, <b>Connected to Shopify</b> and <b>Not Connected</b>. There is no out-of-stock filter on Shopify.

## Stock

We send stock only for products that are linked (green status). You do not need to do anything: when our stock changes, we update your store.

To push stock now, press <b>Update Stock</b> at the top of the page. It sends the current stock of this channel's products. If none of your products is linked yet, it says <b>Nothing to update</b>. The button is on Shopify, WooCommerce, eBay, TikTok Shop and Wix channels, not on Allegro or manual channels.

You can cap or hide stock in the channel settings. See [Why a product shows out of stock in my store](/docs/out-of-stock-in-my-store).

## Other buttons

- <b>Add products</b>, the upload button and <b>Clone portfolio from channel:</b>: add products. See [Finding and adding products](/docs/sourcing-products).
- <b>CSV</b>, <b>⋮</b> (<b>Other Export Options</b>) and <b>Images</b>: download your product data and photos. See [Exporting product data and images](/docs/exporting-product-data).
- <b>Publish ... drafts</b> (eBay only): publishes the listings that were uploaded to eBay as drafts.
- <b>Update all dimensions</b> (Shopify only): sends our current dimensions to all your Shopify products.

## The Logs tab

The <b>Logs</b> tab lists every upload, match and stock update for this channel: <b>Product Code</b>, <b>Type</b> (<b>upload</b>, <b>match</b> or <b>update-stock</b>), <b>Platform</b>, <b>Status</b> (<b>Done</b>, <b>In progress</b> or <b>Failed</b>), the <b>Response</b> from your platform and the <b>Date</b>. Look here first when a product or its stock did not arrive.

## When something goes wrong

The red message on the row, and the <b>Response</b> in <b>Logs</b>, is the answer of your platform. The most common ones:

- **Throttled / too many calls / request timeout / internal error.** Your platform asked us to slow down, or did not answer in time. Nothing is wrong with the product. Try again in a few minutes.
- **The store answered with a web page instead of data, or returned 503, timed out or an empty reply** (WooCommerce). Your own website is down, in maintenance mode, or its security plugin or hosting blocks us. Check your site is online. Ask your hosting to allow our connection, then try again.
- **A product with this SKU already exists in your store / Invalid or duplicated SKU / already present in the lookup table** (WooCommerce). You already have a product with that SKU. Use <b>Match</b> instead of <b>Create new product</b>. If the old product is in the WooCommerce bin, empty the bin first.
- **Invalid or duplicated GTIN** (WooCommerce). Another product in your store already uses that barcode. Remove the barcode from the other product in WooCommerce, or match to it.
- **Cannot list more products: your Shop probation tier allows at most 100 total product listings** (TikTok). This is a TikTok limit for new shops, not a problem with the product. Remove listings you do not need, or ask TikTok to raise your tier.
- **product_weight received 0 / weight cannot be zero** (TikTok). TikTok needs a weight. You cannot change our product weight yourself: ask us in the chat on our website, with the product code.
- **Image must be at least 300:300** (TikTok). One of our images is too small for TikTok. Ask us in the chat on our website, with the product code.
- **Price out of range / incorrect price** (TikTok). TikTok decides the price range your shop may use. Check the range in TikTok Shop Seller Center. If the price we send is outside it, ask us in the chat on our website, with the product code.
- **Category qualification / category is restricted** (TikTok). Apply for the category in the Qualification Center of TikTok Shop Seller Center, then upload again.
- **Requires an active seller account** (TikTok) or **create a seller account** (eBay). Finish your seller account on the platform first.
- **The listing would cause you to exceed the amount you can list this month** (eBay). You reached your eBay selling limit. Ask eBay to raise it, or wait until next month.
- **Invalid data in the associated fulfilment policy** (eBay). Your eBay shipping (fulfilment) policy has a problem. Fix it in eBay, then check the policies chosen in your channel settings.
- **Item specific Type / Brand missing, or custom values for Size no longer supported** (eBay). eBay wants extra details for that category. See [Managing products on eBay](/docs/managing-products-on-ebay).
- **Not allowed to revise an ended item** (eBay). The listing ended on eBay. Unlink the product and create it again.
- **Overseas Warehouse Block Policy** (eBay). If your account is registered in some countries, a red <b>Important Notice</b> shows at the top. eBay may block listings stored overseas. Contact eBay Support to ask for approval.
- **This product line has been discontinued.** We no longer sell it. Remove it from your list and from your store.
