---
title: Exporting product data and images
summary: Download the products of a channel as a CSV file, choose your own columns and filters, and download all product photos as one zip file.
date: 2026-09-25
tags: products, export, csv, images, download, data feed
category: products
help_routes: retina.dropshipping.customer_sales_channels.portfolios.index, retina.catalogue.products.show, retina.catalogue.families.show, retina.catalogue.departments.show, retina.catalogue.sub_departments.show, retina.catalogue.collections.show
shops: awd, dssk, dse
---

<aside class="tldr">
On <b>My Products</b> of a channel there are three download buttons: <b>CSV</b> gives the full details of every product in the list, <b>⋮</b> lets you pick the columns and filters for a smaller CSV, and <b>Images</b> puts all the photos into one zip file. You can also download one product, family, department or collection from its page in the <b>Catalogue</b>.
</aside>

## Where the buttons are

1. Open <b>Channels</b> in the menu, click your channel and open <b>My Products</b>.
2. At the top right you see a group of buttons: <b>CSV</b>, <b>⋮</b> and <b>Images</b>.

The buttons only show when the channel has products and is not closed, and not on the <b>My Bundles</b> tab. The files hold the products of this channel only. To export another channel, open its <b>My Products</b>.

<!-- screenshot: the CSV / ⋮ / Images button group at the top of My Products -->

## Full product details (CSV)

Press <b>CSV</b>. The file downloads straight away. Open it in Excel, Google Sheets or any spreadsheet program. Each row is one product, each column one detail.

The columns are:

- <b>Status</b>: <b>Active</b>, <b>Discontinuing</b> or <b>Discontinued</b>.
- <b>Product code</b>, <b>Product user reference</b> (your own reference for the product, if you set one).
- <b>Department code</b>, <b>Department</b>, <b>Subdepartment code</b>, <b>Subdepartment</b>, <b>Family code</b>, <b>Family</b>.
- <b>Barcode</b>, <b>CPNP number</b> (EU cosmetics number, when the product has one).
- <b>Price</b>: your price for one outer (the pack you order). <b>Units per outer</b>, <b>Unit label</b>, <b>Unit price</b>.
- <b>Unit Name</b>: the product name.
- <b>Unit RRP</b>: recommended retail price for one unit.
- <b>Unit net weight</b> and <b>Package weight (shipping)</b>, in kilograms. <b>Unit dimensions</b>.
- <b>Materials/Ingredients</b>.
- <b>Webpage description (html)</b> and <b>Webpage description (plain text)</b>.
- <b>Country of origin</b>, <b>Tariff code</b>, <b>Duty rate</b>, <b>HTS US</b>.
- <b>Stock</b>: a stock level, not a number: <b>Normal</b>, <b>Low</b> (under 20), <b>VeryLow</b> (under 5), <b>OutofStock</b>, <b>Discontinuing</b> or <b>Discontinued</b>.
- <b>Images</b>: links to the full-size photos, separated by commas.
- <b>Data updated</b>, <b>Stock updated</b>, <b>Price updated</b>, <b>Images updated</b>: when each part last changed.
- <b>Available Quantity</b>: the number of units in stock. It is 0 when the product is not for sale.
- <b>For sale</b>: <b>Yes</b> or <b>No</b>.

Bundles are left out. To include them, open <b>⋮</b> and tick <b>Include bundles</b> first.

## Your own columns and filters

Press <b>⋮</b> (<b>Other Export Options</b>). A panel opens:

- <b>Bundles</b>: tick <b>Include bundles</b> to add your bundles. It is off by default and applies to both CSV downloads.
- <b>Columns to Export</b>: tick the columns you want. <b>Select All</b> and <b>Deselect All</b> are at the top. The columns are the product, department, sub-department and family codes and names, barcode, materials, dimensions, weights, origin and customs codes, <b>Stock</b> (a number), <b>Status</b> (<b>In stock</b> or <b>Out of stock</b>), <b>For sale</b> and <b>Data updated</b>.
- <b>Product State</b>: <b>Active</b>, <b>Discontinuing</b>, <b>Discontinued</b>. Only <b>Active</b> is ticked at the start.
- <b>Product Sale Status</b>: <b>Exclude products that are not for sale</b>, <b>Exclude products that are out of stock</b>, <b>Only products that are not for sale</b>.

Press <b>Export Extended Properties</b>. The file opens in a new tab and downloads. This file has no prices, descriptions or image links: use the full <b>CSV</b> for those.

<!-- screenshot: the Export Options panel with Columns to Export, Product State and Product Sale Status -->

## All product photos (zip)

1. Press <b>Images</b>. A window says <b>Your download images request is being processed.</b> We collect the photos of every product in the list.
2. When it is ready the window says <b>Your images are ready for download.</b> Press <b>Download</b> and save the zip file.
3. The button now says <b>Download images</b>. Hold the mouse over it to see how long the link still works. The link expires one day after it was made.

Each photo is named with the product code and a number, for example <b>abc-01__12345.jpg</b>, so you can see which product it belongs to.

Whenever products in the channel are added or changed, the old zip is deleted. Press <b>Images</b> again to make a new one.

There are no product videos in this download.

## One product, family or collection

In <b>Catalogue</b>, open a product, family, sub-department, department or collection. At the top right:

- <b>CSV</b> downloads its products with the same columns as the full CSV.
- On product, family and collection pages, press <b>⋮</b> and choose <b>images</b> under <b>Select another download file type</b> to download their photos as a zip file.

On the catalogue pages of our website, each family and product in the list has two download icons: <b>Download products (csv)</b> and <b>Download images (zip)</b>.

## When something goes wrong

- **I do not see the CSV and Images buttons.** The channel has no products yet, the channel is closed, or you are on the <b>My Bundles</b> tab. Add products first, or go back to the <b>My Products</b> tab.
- **The CSV has fewer products than My Products.** The full <b>CSV</b> leaves out bundles. The <b>Export Extended Properties</b> file also uses the <b>Product State</b> and <b>Product Sale Status</b> filters: tick all states to get everything.
- **"Select at least one column".** Tick at least one column under <b>Columns to Export</b>.
- **The images link says Expired or does not open.** Press <b>Images</b> again to make a new zip.
- **The zip only has a file called error.txt.** None of the products in the list has a photo. Check that the channel has products.
- **Excel shows strange letters.** Open the file with <b>Data → From Text/CSV</b> and choose UTF-8, or open it in Google Sheets.
- **"The data feed for ... is not available yet, please try again later."** The file for that family or department is still being made. Try again in a few minutes.
