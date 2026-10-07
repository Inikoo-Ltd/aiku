---
title: Managing products on the Manual/API channel
summary: Add the products you sell to My Products on a Manual/API channel, import them from a spreadsheet or another channel, download your product data and images, and remove products you no longer sell.
date: 2026-09-25
tags: manual, api, my products, portfolio, add products, import, csv, images
category: products
series: manual
order: 2
help_routes: retina.dropshipping.customer_sales_channels.portfolios.index, retina.dropshipping.customer_sales_channels.portfolios.show, retina.dropshipping.customer_sales_channels.portfolios.bulk_import_history
shops: awd, dssk, dse
---

<aside class="tldr">
<b>My Products</b> is the list of products you sell in a channel. Open it under your Manual/API channel and press <b>Add products</b> to pick products from our catalogue. On a Manual/API channel nothing is uploaded anywhere: the list is for your own use and for the API. You do not need it to place orders by hand. To stop selling a product, press the <b>X</b> button on its row.
</aside>

## Open My Products

Go to your Manual/API channel in the menu and open <b>My Products</b>. You can also press <b>View all</b> on the <b>Products</b> box of the channel page.

If the list is empty, the page says <b>You don't have any items in your portfolio</b> and shows an <b>Add Product</b> button.

Each product row shows the picture, the name and code, the stock we have (<b>Stocks:</b>), the weight, your price (<b>Price:</b>) and the recommended retail price (<b>RRP:</b>).

## Add products

1. Press <b>Add products</b>.
2. A window <b>Add products to your product</b> opens.
3. Choose what to search by: <b>Product</b> searches product names and codes, <b>Department</b>, <b>Sub-department</b> and <b>Family</b> find the products in a group with that name.
4. Type in the search box and tick the products you want.
5. Press <b>Add … products and close</b>. The number is how many you ticked.

<!-- screenshot: the Add products window with the Product / Department / Sub-department / Family filter and the Add products and close button -->

You see <b>Successfully added portfolios</b> and the products appear in the list.

## Add many products at once

### From a spreadsheet

1. Press the upload button next to <b>Add products</b> (tooltip <b>Import from xlsx file</b>).
2. In the <b>Bulk Import Portfolios</b> window, press <b>Download template (.xlsx)</b>.
3. Fill in the <b>sku</b> column with our product codes, one per row. The <b>title</b> column is optional.
4. Upload the file.

Rows are skipped when the code does not exist in our shop or the product is not for sale. The upload history shows what was added and what failed.

### From another channel

If you already have products in another channel, you can copy them. Press the button with three dots next to <b>Add products</b>. Under <b>Clone portfolio from channel:</b> pick the channel to copy from. The number in brackets is how many products it has. The copy runs in the background and the page reloads when it is done.

## Find products in your list

Use the search box, or the filter buttons above the list:

- <b>Only For Sale</b>: products you can order now.
- <b>Not For Sale</b>: products we are not selling at the moment.
- <b>Discontinued</b>: products we will not sell again.
- <b>Out of stock</b>: products with no stock right now.

A crossed-out box icon means the product is discontinued. Its tooltip says <b>This product line has been discontinued. Please remove this item</b>. A crossed-out money icon means <b>This product line is currently not for sale</b>. Take these products off your own website so your buyers cannot order them.

## Get product data and images for your website

On a Manual/API channel we do not upload products to your website. Take the data from here:

- <b>CSV</b>: downloads your product list with prices, stock and descriptions.
- The three-dots button next to <b>CSV</b> opens <b>Export Options</b>. Choose the columns, the <b>Product State</b> and the <b>Product Sale Status</b> you want, then press <b>Export Extended Properties</b>. Tick <b>Include bundles</b> to add your bundles.
- <b>Images</b>: prepares a download of the pictures of your products. When it is ready, press <b>Download images</b>. The link works only for a limited time, shown on the button's tooltip.
- Through the API, your system can read the same list, and download it as a CSV or JSON feed. See [The Manual/API channel](/docs/manual-and-api-channel).

Stock and prices change. Download the list again, or read it through the API, often enough to keep your website right.

## Remove a product

Press the <b>X</b> button on the product's row (tooltip <b>Remove product from list</b>). The product leaves your list. Orders you already placed with it are not changed. You can add it again later with <b>Add products</b>.

## When something goes wrong

- <b>I cannot find a product in the Add products window.</b> Check you are searching in the right tab: <b>Product</b> looks for product names and codes, <b>Family</b> and <b>Department</b> look for group names. The window does not show products that are already in your list, products that are not for sale and discontinued products.
- <b>My spreadsheet upload skipped rows with "SKU not found in this shop".</b> The code in the <b>sku</b> column is not one of our product codes in this website. Copy the code exactly as it shows on the product.
- <b>My spreadsheet upload skipped rows with "Product is not for sale".</b> We do not sell that product at the moment. Leave it out.
- <b>A product shows as discontinued or not for sale.</b> You cannot order it. Remove it from your own website and from <b>My Products</b>.
- <b>A product is out of stock.</b> It stays in your list. Use <b>Out of stock</b> to find these products and hide them on your website until they are back.
- <b>The image download link does not work any more.</b> The link expires. Press <b>Images</b> again to make a new one.
- <b>My products are not on my website.</b> We never upload from a Manual/API channel. Load them yourself with the CSV download or the API. If you sell on a platform shown on the <b>Add Sales Channel</b> page, such as Shopify, WooCommerce, eBay or TikTok Shop, connect that platform as its own channel and products are uploaded for you.
