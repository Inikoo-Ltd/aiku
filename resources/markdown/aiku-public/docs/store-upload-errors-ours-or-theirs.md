---
title: Store upload errors: ours or theirs?
summary: When a dropshipping customer says their products will not upload to eBay, Shopify, WooCommerce, TikTok Shop, Wix or Allegro, read the platform's answer in aiku first. Most of the time it is the platform's own rule and the customer guide already tells them what to do.
date: 2026-09-29
tags: dropshipping, crm, sales channels, ebay, shopify, woocommerce, tiktok, wix, allegro, tickets
category: crm
help_routes: grp.org.shops.show.crm.customers.show.customer_sales_channels.index, grp.org.shops.show.crm.customers.show.customer_sales_channels.show.portfolios
---

<aside class="tldr">
When a customer's products will not upload to their store, aiku keeps the exact answer the platform gave. Open the customer's channel, then <b>Portfolios</b> &rarr; <b>Logs</b>, and read the <b>Response</b>. If the message is about the customer's account, limits, policies, categories or their own website, it is <b>the platform's rule</b>: only the customer can fix it, and the customer guide on our website tells them how. Send them that guide. Raise a ticket only when the message is about us or our product data, or when nothing explains it.
</aside>

## Read the answer first

1. Open the customer, then the <b>Channels</b> tab. Each channel shows its state: <b>closed</b>, or green and red icons for <b>App installed ok</b>, <b>Exist in platform</b> and <b>Platform status</b>. Hold the mouse over an icon to read it.
2. Click the channel, then <b>Portfolios</b>. The products are listed with the same icons. A red cross on <b>Platform status</b> means the product is not live in the store.
3. Open the <b>Logs</b> tab. Every upload, stock update and retry is a row with a <b>Status</b> of <b>Done</b>, <b>In progress</b> or <b>Failed</b>. The <b>Response</b> column is what the platform said. Click the code icon, <b>See the answer of the platform</b>, to read the whole answer.

A product with no row in <b>Logs</b> has not been sent yet. That is not an error: the customer has added it but not pressed <b>Create new product</b>, or it is waiting its turn.

## Whose problem is it?

<b>The platform's</b>, when the answer talks about:

- a limit: items per month, probation, number of products;
- the customer's account: not verified, not a seller account, inactive, restricted;
- the customer's settings on the platform: postage or return policies, warehouses, complaint terms, categories they must apply for;
- the customer's own website being down, slow, or blocking us (WooCommerce and Wix);
- a product already in their store with the same SKU or barcode.

We cannot change any of these, and neither can the engineers. The customer has to do it on the platform.

<b>Ours</b>, when the answer talks about:

- product data we own: a missing weight, a missing brand or type, a picture too small, a missing barcode the customer cannot add;
- aiku itself, or a message that names us;
- a <b>Failed</b> row with no message at all;
- the same error suddenly on many customers of the same platform.

<b>Reconnecting does not reset the platform's rules.</b> Deleting the channel and connecting it again, or changing the account type, changes nothing if the limit or restriction sits on the customer's account. A new channel with the same account hits the same wall.

## eBay

Guide to send: <b>managing-products-on-ebay</b>, and <b>connecting-ebay</b> for connection problems.

- <b>"This listing would cause you to exceed the number of items you can list"</b> or <b>"… the amount you can list this month"</b>: the customer's eBay selling limit. Only eBay raises it, at ebay.co.uk/help/selling/listings/selling-limits. A business account does not raise it on its own.
- <b>"invalid data in the associated fulfilment policy"</b>: the postage policy they chose has no postage service. They fix it in eBay.
- <b>Seller account not finished</b>: they finish seller registration in eBay.
- <b>"not allowed to revise an ended item"</b> or <b>"This Offer is not available"</b>: the listing ended on eBay. They press <b>Create new product</b>.
- <b>"improper words" or "in violation of eBay policy"</b>: eBay's own review. Only eBay can answer.
- <b>Overseas Warehouse Block Policy</b>: they ask eBay for approval.
- <b>Ours:</b> <b>"The item specific Brand is missing"</b> (or Type, Item Length, Item Width) and <b>"custom values for Size are no longer supported"</b>. Raise a ticket with the product code.

## Shopify

Guides to send: <b>managing-products-on-shopify</b>, <b>connecting-shopify</b>, <b>shopify-fulfilment-location</b>.

- <b>Channel not connected yet</b>: the app was not installed. They press <b>Click here to install</b> and then <b>Install</b> in Shopify.
- <b>"No Shopify location, the AW fulfilment service is not installed"</b>: the same, the install was not finished.
- <b>A product with the same SKU already exists</b>, <b>"No variant on Shopify matches this sku"</b>, <b>"More than one variant … has the sku"</b>: their store's products. They match or fix the SKU in Shopify.
- <b>"Throttled"</b>, <b>HTTP 502 or 504</b>: Shopify was busy. They try again later.
- <b>Products show as sold out in Shopify</b>: the <b>aiku-</b> location is missing from their shipping profile.
- <b>Ours:</b> <b>"You need to add option values"</b> when they want to link to existing variants. That needs us to switch the option on, so raise a ticket.

## WooCommerce

Guides to send: <b>managing-products-on-woocommerce</b>, <b>connecting-woocommerce</b>.

Almost every WooCommerce error is the customer's website: down, slow, in maintenance, or blocking our servers with a firewall, security plugin or Cloudflare. The message names the problem and, where it helps, lists our IP addresses for their hosting company.

- <b>A web page instead of data, 503, timeout, empty reply</b>: their site. They check it opens and ask their hosting to allow us.
- <b>SKU or GTIN already exists</b>: a product in their store, sometimes in the WooCommerce trash.
- <b>Could not save the product images</b>: their uploads folder. Their hosting fixes it.
- <b>Not allowed to create products</b> or <b>rejected the credentials</b>: the keys lost permission or were deleted. They reconnect.
- <b>The store no longer accepts our keys</b>: the channel page shows an amber box with a reconnect link and a <b>Copy</b> button. Send the customer that link. Only the store owner can authorise again.

## TikTok Shop

Guides to send: <b>connecting-tiktok-shop</b>, <b>tiktok-shop-warehouse</b>, <b>tiktok-shop-shipping-template</b>.

- <b>Shop probation period</b> or <b>probation tier</b>: new TikTok shops can list only a few products. The message says how many. They wait, or remove products they do not sell.
- <b>Requires an active seller account</b>, <b>category qualification</b>, <b>certifications</b>, <b>manufacturer is required</b>, <b>requires a return warehouse</b>: all set in TikTok Seller Center.
- <b>Incorrect price</b>: their selling price is outside what TikTok allows. They change it in <b>My Products</b>.
- <b>No warehouse matches 0</b> or <b>no warehouse yet</b>: they add a default warehouse in Seller Center, then press <b>Save</b> on the channel.
- <b>Ours:</b> <b>product_weight</b> received <b>0</b>, a picture under <b>300 x 300 pixels</b>, and <b>"the warehouse does not belong to this shop"</b>. Raise a ticket with the product code.

## Wix

Guides to send: <b>uploading-products-to-wix</b>, <b>connecting-wix</b>.

- <b>"AW Connect isn't supported with your site"</b>: Wix refuses before our app is reached. Wix Stores must be installed and on the newer catalogue.
- <b>Wix Stores is not installed</b>, <b>channel not connected yet</b>: they press <b>Try to reconnect</b> and install on the same site.
- <b>No pictures</b>, <b>out of stock</b>: the guide walks them through it.

## Allegro

Guides to send: <b>syncing-products-to-allegro</b>, <b>connecting-allegro</b>.

- <b>"You do not have any Complaints Terms"</b>, <b>inactive or unverified account</b>: set up in Allegro.
- <b>Missing mandatory parameters</b>, <b>no matching category</b>: the category needs details we do not have. They choose another product, or create the offer in Allegro and <b>Match</b> it.
- <b>Channel not connected</b>: Allegro access runs out. They press <b>Reconnect</b>.
- <b>Ours:</b> <b>"No shipping price list set"</b> that stays after they connect the same account again.

## Sending the guide

The customer guides are on the customer's own website, at <b>/docs/</b> and the guide's name: <b>aw-dropship.com</b> for the UK, <b>aw-dropship.eu</b> for Europe, <b>aw-dropship.es</b> for Spain. For example <b>https://www.aw-dropship.com/docs/managing-products-on-ebay</b>.

A reply that works:

> The message comes from eBay, not from us: your eBay account has a monthly listing limit. Only eBay can raise it, here: https://www.ebay.co.uk/help/selling/listings/selling-limits?id=4107. Our guide explains this and the other eBay messages: https://www.aw-dropship.com/docs/managing-products-on-ebay

Say who the message comes from, what they need to do, and give the link. The customer does not need to wait for an engineer to hear that.

## When you do raise a ticket

Raise it from the chat (see [Raising a ticket from a chat](/docs/raising-a-ticket-from-a-chat)) and include:

- the link to the customer's channel in aiku;
- the product code;
- the <b>Response</b> text from <b>Logs</b>, copied, not retyped;
- what you already checked in the customer guide.

A ticket with the platform's answer in it can be answered in minutes. A ticket that says "error when uploading" starts with an engineer doing these same steps.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Channel state:</b> customer &rarr; <b>Channels</b> &rarr; the icons on the channel row.</li>
<li><b>The platform's answer:</b> customer &rarr; <b>Channels</b> &rarr; the channel &rarr; <b>Portfolios</b> &rarr; <b>Logs</b> &rarr; <b>Response</b>, or the code icon for the full answer.</li>
<li><b>WooCommerce reconnect link:</b> the channel page &rarr; the amber box &rarr; <b>Copy</b>.</li>
<li><b>Send products again:</b> <b>Portfolios</b> &rarr; <b>Force Sync</b>.</li>
<li><b>Customer guides:</b> the customer's website &rarr; <b>/docs/</b> and the guide's name.</li>
</ul>
</aside>
