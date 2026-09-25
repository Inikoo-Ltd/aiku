---
title: Adding and syncing products to Allegro
summary: Add our products to your Allegro channel, create them as Allegro offers or link them to offers you already have, and understand the messages Allegro sends back.
date: 2026-09-25
tags: allegro, products, offers, upload, match, price, currency
category: products
series: allegro
order: 2
help_routes: retina.dropshipping.customer_sales_channels.portfolios.index, retina.dropshipping.customer_sales_channels.edit
shops: dssk, dse
---

<aside class="tldr">
Open your Allegro channel, go to <b>My Products</b> and press <b>Add products</b>. Pick the products and press <b>Add</b>. Then press <b>Upload all as new product</b> to create them as offers on Allegro, or <b>Match all with default product</b> to link them to offers you already have. A product with three green ticks is live on Allegro. If an upload fails, hold the mouse over the red message on its row to see why.
</aside>

## Before you start

Your Allegro channel must show three green ticks on its dashboard, and your Allegro account needs complaint terms (<b>Warunki reklamacji</b>). See the guide on connecting your Allegro account.

## Add products to My Products

1. Open your Allegro channel from the menu and go to <b>My Products</b>.
2. If the list is empty, press <b>Add Product</b> in the middle of the page. Otherwise press <b>Add products</b> at the top right. A window opens with our catalogue.
3. Search by product name or code, or browse by <b>Department</b>, <b>Sub-department</b> or <b>Family</b>.
4. Tick the products you want. The button at the top right shows how many you picked, for example <b>Add 5</b>. Press it.

<!-- screenshot: the Add products window with some products ticked and the Add button -->

The products are now in <b>My Products</b> but not on Allegro yet. A yellow message says <b>You have ... products not synced yet</b>.

## Create the offers on Allegro

- To create all of them, press <b>Upload all as new product</b> in the yellow message. A window shows <b>Uploading Portfolios...</b> and counts the products. When it says <b>Uploading Complete!</b> the page reloads by itself. If it does not, refresh the page.
- To create only some, tick them in the list and press <b>Create New (N)</b>, where N is how many you ticked.
- To create one, press <b>Create new product</b> on its row.

<!-- screenshot: My Products with the yellow "products not synced yet" message and the Upload all as new product button -->

For each product we:

- Look for the product in the Allegro catalogue by its barcode to find its category. If Allegro does not know the barcode, Allegro suggests a category from the name of the product's sub-department.
- Propose the product to the Allegro catalogue, or use the catalogue product Allegro already has.
- Create a <b>Buy Now</b> offer and publish it as active straight away.
- Use the <b>AW-EU-</b> shipping price list and the return policy we made when you connected.

What the offer contains:

- The title, cut to 75 characters. Allegro does not accept longer titles.
- Your description. Allegro only accepts plain text, bold text and paragraphs, so we remove other formatting. Line breaks become spaces.
- The text is sent in English. Allegro translates it for buyers in its own languages.
- Your price, changed into the currency of your Allegro market: PLN for Poland, CZK for the Czech Republic, EUR for Slovakia and HUF for Hungary. We use the current exchange rate from the currency of your dropshipping account. Prices in HUF are rounded up to the next 5 HUF, because Allegro Hungary only accepts those.
- Our stock, capped by <b>Max Quantity To Advertise</b> if you set it in <b>Manage Sales Channel</b>, <b>Manage Stock</b>.
- A dispatch time of 24 hours.

## Link offers you already have on Allegro

If the product is already on Allegro as your own offer, link it instead of creating a second offer. Linking does not change your Allegro offer.

- <b>Match all with default product</b> in the yellow message links every product whose external ID (<b>sygnatura</b>) on Allegro is the same as our product code. Products with no match are left alone. Linking runs in the background, so refresh the page after a moment.
- To link only some products, tick them and press <b>Match (N)</b>.
- For one product, press <b>Match it with an existing product in your shop</b> on its row, pick the offer and press <b>Link ... to selected item on your platform</b>.

To match, put our product code in the external ID field of your Allegro offer first.

## Check that a product is live

Each row has three small ticks in <b>Status</b>: <b>Has valid platform product id</b>, <b>Exist in platform</b> and <b>Platform status</b>. Three green ticks mean the offer is on Allegro and linked. A green circle next to them means the last upload was fine.

To see your offers in Allegro, log in to <b>Moje Allegro</b> and open your offers list.

## Change or remove products

- You cannot edit the title, description or price of an Allegro offer from <b>My Products</b>, and we never change an offer after we create it. Change them in Allegro.
- <b>Unlink (N)</b> stops linking the ticked products to their offers. The offers stay on Allegro.
- <b>Unlink & Delete (N)</b>, or the bin on a row, removes the product from <b>My Products</b>. We do not delete anything on Allegro: the offer stays there. End it in Allegro if you no longer want to sell it.

## When something goes wrong

Hold the mouse over the red message on a row to read what Allegro answered. The most common messages:

<b>You do not have any Complaints Terms.</b>
Create complaint terms (<b>Warunki reklamacji</b>) in your Allegro sales settings. Then upload again.

<b>The user with an inactive or unverified account cannot create new product proposals.</b>
Allegro has not finished checking your account. Finish the verification in Allegro, then upload again.

<b>No shipping price list set.</b>
The <b>AW-EU-</b> shipping price list is missing from your Allegro account. Connect the same Allegro account again from <b>Create Channels</b>: we create the list again. If the message stays, ask us in the chat on our website.

<b>You cannot create a product without providing correct values for all the required parameters: [...]</b> or <b>Missing mandatory parameters: ...</b>
The category Allegro chose needs details we do not have for this product, for example a length or a barcode (EAN). Choose a different product, or create the offer yourself in Allegro and link it with <b>Match</b>.

<b>Allegro has no matching category for "..."</b>
Allegro could not find a category for the product. Create the offer yourself in Allegro and link it with <b>Match</b>, or choose a different product.

<b>Unable to get the ... exchange rate.</b>
We could not change the price into your Allegro currency at that moment. Try the upload again later.

<b>Upload all as new product is missing</b>
The button, and <b>Add products</b>, hide for a short time after your Allegro account did not answer. Refresh the page after a moment. If it stays missing, check that the channel dashboard still has three green ticks. If not, press <b>Reconnect</b>.
