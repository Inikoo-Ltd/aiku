---
title: Managing products on WooCommerce
summary: Add our products to your WooCommerce channel, create them in your store or link them to products you already sell, keep stock up to date, and fix upload errors.
date: 2026-09-25
tags: woocommerce, products, upload, match, sku, stock
category: products
series: woocommerce
order: 2
help_routes: retina.dropshipping.customer_sales_channels.show, retina.dropshipping.customer_sales_channels.portfolios.index, retina.dropshipping.customer_sales_channels.edit
shops: awd, dssk, dse
---

<aside class="tldr">
Open your WooCommerce channel and go to <b>My Products</b>. Press <b>Add products</b> and choose the products you want to sell. Then send each one to your store: <b>Create new product</b> makes a new product in WooCommerce, and <b>Match with this product</b> links it to a product you already have in your store. A green tick means the product is live and we keep its stock up to date.
</aside>

## Open your product list

1. Open <b>Channels</b> in the menu and click the name of your WooCommerce store.
2. On the channel dashboard, press <b>View all</b> under <b>Products</b>. The <b>My Products</b> page opens.

If the page shows <b>Your channel is not connected yet to the platform</b>, fix the connection first. See [Connecting your WooCommerce store](connecting-woocommerce).

## Add products to your list

1. Press <b>Add products</b>. The <b>Select products to be added to shop</b> window opens.
2. Search by name or code. You can also pick a whole <b>Department</b>, <b>Sub-department</b> or <b>Family</b> instead of single products.
3. Tick the products you want. Press <b>Add</b>. The button shows how many you selected.

The products are now in your list, but they are not in your WooCommerce store yet. You need to create or match them first.

You can also add many products at once from a spreadsheet with the upload button next to <b>Add products</b> (<b>Import from xlsx file</b>). If you have products in another channel, the <b>⋮</b> button lets you <b>Clone portfolio from channel</b>.

<!-- screenshot: the Select products to be added to shop window with a few products ticked and the Add button -->

## Send products to your store

Each product has a <b>Woo Commerce product</b> column. What you see there depends on the product:

- <b>Create new product</b>: makes a new product in your WooCommerce store, with the name, description and price from your product list, and our images, SKU, barcode, weight, dimensions and stock.
- <b>Match with this product</b>: we found a product in your store with the same SKU, or a similar name. Check it is the right one, then press it to link the two. Use this when you already sell the product and do not want a second copy.
- <b>Choose another product from your shop</b> (when we found a possible match) or <b>Match it with an existing product in your shop</b> (when we found none): opens a list of the products in your store. Search for the product, select it and press <b>Link ... to selected item on your platform</b>.

When it works, the product shows a green tick and the name of your WooCommerce product. From then on we keep its stock up to date. To link it to a different WooCommerce product later, press <b>Change linked listing</b>.

When you match a product, we only link it and update its stock. We do not change the name, description, price or images you already have in WooCommerce.

<!-- screenshot: My Products rows showing Create new product, Match with this product, and a green tick on a linked product -->

### Many products at once

- Tick several products in the list. Buttons appear above the list: <b>Create New</b> sends them all as new products, <b>Match</b> links them to products in your store with the same SKU.
- If some products are not in your store yet, you see <b>You have ... products not synced yet</b>. Press <b>Upload all as new product</b> to create them all, or <b>Match all with default product</b> to link every product that has the same SKU in your store.

Large uploads run in the background and show a progress window. You can keep working while they run.

Matching looks for our SKU, or our product code, in your store. Upper and lower case do not matter. If your SKUs are different from ours, use <b>Match it with an existing product in your shop</b> and choose the product yourself.

## What we send to WooCommerce

- Name, description and price from your product list.
- Our images, SKU and barcode (as the GTIN, UPC, EAN or ISBN).
- Weight in the unit your store uses, and dimensions when we have them.
- Country of origin and ingredients as product attributes, and links to product documents in the description.
- The stock you can sell. Products that are for sale are published. Products that are out of stock, coming soon or not ready yet are saved as drafts.

We do not choose a category for you. New products arrive without a category, so add your own categories in WooCommerce.

## Stock and prices

We send stock changes to your store automatically. Press <b>Update Stock</b> at the top of <b>My Products</b> to push the current stock of all your products to this channel now.

In <b>Manage Sales Channel</b> you can change how stock is shown:

- <b>Stock Update</b>: turn automatic stock updates on or off.
- <b>Max Quantity To Advertise</b>: the highest stock number we show in your store, even when we have more.
- <b>Stock Threshold</b>: when our stock falls to this number, the product shows as out of stock in your store.

Your <b>Pricing Policy</b> in <b>Manage Sales Channel</b> sets the price of products you add from now on. It does not change products already in your list. To change their prices, tick them and press <b>Edit Price</b>.

## Remove products

There are three ways to remove a product. Choose carefully, because the skull button also deletes the product from your WooCommerce store.

- The skull button on a product row asks you to confirm, then removes the product from your list and, if it is linked, permanently deletes it from your WooCommerce store. It does not go to the WooCommerce trash.
- <b>Unlink & Delete</b> (after you tick products) removes the ticked products from your list, but keeps them in your WooCommerce store. They are no longer linked, so we stop updating their stock.
- <b>Unlink</b> (after you tick products) keeps the products in your list and in WooCommerce, but breaks the link. We stop updating their stock. You can match them again later.

If you delete a linked product in WooCommerce yourself, we remove it from your list too.

Products we no longer sell show a red sign. <b>This product line has been discontinued. Please remove this item</b> means you should remove it from your store. <b>This product line is currently not for sale</b> means you cannot upload it at the moment.

## Check what happened

Open the <b>Logs</b> tab (the clock icon on the right of the tabs) to see each upload, whether it worked, and the message your store sent back.

The <b>Status</b> column shows three ticks for each product: <b>Has valid platform product id</b>, <b>Exist in platform</b> and <b>Platform status</b>. Three green ticks mean the product is linked and live.

## When something goes wrong

If an upload fails, the product row shows the message from your store and a short tip. The most common ones:

- <b>The store answered with a web page instead of data</b>, a 503 error, a timeout, or an empty reply: your website is down, too slow, in maintenance mode, or blocks us. This is the most common upload problem. Check that your site opens in the browser, ask your hosting company to allow our servers, then upload again.
- <b>A product with this SKU already exists in your store</b>, <b>Invalid or duplicated SKU</b>, or <b>product with SKU ... already present in the lookup table</b>: your store already has a product with this SKU. When you press <b>Create new product</b> we try to link to that product by ourselves. If the message still shows, match the product by hand with <b>Match it with an existing product in your shop</b>. If you cannot find the product in your store, look in the WooCommerce trash: a deleted product still holds the SKU until you delete it permanently.
- <b>Invalid or duplicated GTIN</b>: another product in your store already has the same barcode (GTIN, UPC, EAN or ISBN). Match with that product, or remove the barcode from the other product in WooCommerce, then upload again.
- <b>Your store could not save the product images</b>: the WordPress uploads folder is not writable. Ask your hosting company to fix the folder permissions, then upload again.
- <b>The account connected to your store is not allowed to create products</b> (or to edit or read them): the keys do not have <b>Read/Write</b> permission. Reconnect the channel with an administrator account.
- <b>Your store rejected the credentials</b>: the keys were deleted or changed in WooCommerce. Press <b>Try to reconnect</b> on the channel page.
- <b>This product no longer exists in your store</b>: the product was deleted in WooCommerce. Create it again or match it with another product.
- The <b>Add products</b> button is missing: your store did not answer the last time we tried to reach it, so we paused the channel. Check that your website is online. The button comes back after we reach your store again.

Problems on your own website, such as it being down, slow or blocking us, and product rules you set in WooCommerce can only be fixed by you or your hosting company.
