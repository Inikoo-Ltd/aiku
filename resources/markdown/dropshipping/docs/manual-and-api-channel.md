---
title: The Manual/API channel
summary: Create a Manual/API channel to sell from your own website, marketplace or app, place orders by hand or send them to us through our API.
date: 2026-09-25
tags: manual, api, sales channel, own website, api token, integration
category: sales-channels
series: manual
order: 1
help_routes: retina.dropshipping.customer_sales_channels.index, retina.dropshipping.customer_sales_channels.create, retina.dropshipping.customer_sales_channels.show, retina.dropshipping.customer_sales_channels.edit, retina.dropshipping.customer_sales_channels.api.dashboard
shops: awd, dssk, dse
---

<aside class="tldr">
Use a <b>Manual/API</b> channel when your shop is not on one of the platforms we connect to, or when you want to type orders yourself. Go to <b>Channels</b>, press <b>Add Sales Channel</b>, then <b>Create</b> on the <b>Manual/API</b> card and give it a name. You then add products to <b>My Products</b>, add your buyers as <b>Clients</b> and create orders for them, by hand or through the API.
</aside>

## When to use a Manual/API channel

A Manual/API channel is not linked to any store. Nothing is uploaded to a website and no orders arrive by themselves. Use it when:

- You sell on your own website, on a marketplace we do not connect to, on social media or by phone, and you want to send each order to us yourself.
- You have your own system or developer and want to send orders to us through our API.

If your shop is on a platform shown on the <b>Add Sales Channel</b> page, such as Shopify, WooCommerce, eBay or TikTok Shop, connect that platform instead. Then products are uploaded for you and orders come in by themselves.

You can have more than one Manual/API channel, for example one per website.

## Create the channel

1. Open <b>Channels</b> in the menu. You see the list of your <b>Sales Channels</b>.
2. Press <b>Add Sales Channel</b>. The page shows <b>Select channel you want to create</b>.
3. On the <b>Manual/API</b> card press <b>Create</b>.
4. A window <b>Create platform manual</b> opens. Type a name for the channel, for example your website name. The name can be up to 28 characters.
5. Press <b>Create</b>.

<!-- screenshot: the Add Sales Channel page with the Manual/API card and its Create button, and the Create platform manual window -->

You see the message <b>Your Manual store has been created.</b> and the channel page opens.

Each of your channels needs its own name. If the name is already used by another of your channels, the window shows an error. Choose a different name.

## Your channel page

The channel page has the name of your channel as its title and the heading <b>Manual/API order management</b>. It shows three boxes, each with a <b>View all</b> link:

- <b>Orders</b>: the orders you placed in this channel.
- <b>Clients</b>: the people you send orders to.
- <b>Products</b>: the products in your <b>My Products</b> list.

In the menu, under the channel name, you find:

- <b>Baskets</b>: orders you started and did not pay yet.
- <b>My Products</b>: the products you sell in this channel. See [Managing products on the Manual/API channel](/docs/managing-products-on-the-manual-channel).
- <b>Clients</b>: your buyers. See [Managing clients](/docs/managing-clients).
- <b>Orders</b>: your placed orders. See [Placing orders manually](/docs/placing-orders-manually).
- <b>API</b>: tokens and documentation for connecting your own system.

The usual way to work is:

1. Add the products you sell to <b>My Products</b> with <b>Add products</b>. This is needed for the API. For orders you type yourself it is optional: the basket lets you pick any product we sell.
2. When you get an order, open <b>Clients</b>, find your buyer or add them.
3. On the client page press <b>Create Order</b>, add the products and quantities and pay.

## Change the name or close the channel

To rename the channel, press <b>Edit</b> on the channel page and change <b>Store name</b>.

To close a channel, go to <b>Channels</b> and press the close button in the <b>Action</b> column (tooltip <b>Close channel</b>). The window asks <b>Are you sure you want to close this channel?</b> and warns <b>This operation is irreversible.</b> A closed channel leaves the menu. Your past orders and invoices are kept.

## Connect your own system with the API

The API lets your website or app do by itself what you do on the channel pages: read our product catalogue with live prices, add products to <b>My Products</b>, create and change clients, create orders, add products to them, submit them and follow them. You can also download your <b>My Products</b> list as a CSV or JSON feed to load products into your own website.

Open <b>API</b> under your channel. The page has these tabs:

- <b>Overview</b>: how to connect, the base address of the API and the <b>API documentation</b> button. The documentation lists every endpoint with examples.
- <b>API tokens</b>: the tokens for this channel.
- <b>API calls</b>: the requests your system made.
- <b>History</b>: changes made on your account.

### Get a token

1. Press <b>Generate API token</b>.
2. If the token is only for reading data, tick <b>Read only (cannot create, change or submit orders)</b>.
3. Press <b>Click to Generate</b>.
4. Copy the token with the copy icon and keep it safe. The window says <b>Put this token in a safe place, you won't be able to see it again.</b> The short label in the token list is only a name, not the token.

Send the token with every request in the header <b>Authorization: Bearer</b> followed by your token. Each token belongs to one channel: products, clients and orders your system creates go to that channel. To stop a token working, delete it in the <b>API tokens</b> tab.

<!-- screenshot: the API page Overview tab with the API documentation and Generate API token buttons -->

### Test first on staging

The <b>Overview</b> tab also has <b>Open staging mirror</b>. Staging is a separate copy of the site where you can test without real orders or payments. Log in with the same email and password. Staging is reset regularly with a fresh copy, which erases what you created there. Tokens from the real site do not work on staging: generate a separate token on staging, and a new one after each reset. The staging base address is shown on the <b>Overview</b> tab.

### How API orders are paid

When your system submits an order, we pay it first from your account balance, then from your saved cards. Add a card before you start. Once you have a token, the menu shows <b>Saved Cards</b>. While no card is saved, the API page shows <b>You have no cards saved yet.</b> with an <b>Add card</b> button.

If neither your balance nor your cards cover the order, the order is marked <b>Unpaid</b> and does not go to the warehouse. Add money to your balance with <b>Top Up</b>, open the order and press <b>Pay … with balance</b>. The button shows when your balance covers the amount due.

## When something goes wrong

- <b>The name is already taken when I create the channel.</b> Another of your open channels has that name. Type a different name. The name of a closed channel can be used again.
- <b>My orders are not arriving by themselves.</b> A Manual/API channel never collects orders from a website. Create them on the client page, or send them from your system through the API. If you sell on a platform shown on the <b>Add Sales Channel</b> page, connect that platform as its own channel.
- <b>My products are not on my website.</b> We do not upload anything from a Manual/API channel. Load them into your website yourself, with the CSV download on <b>My Products</b> or through the API.
- <b>I lost my API token.</b> It cannot be shown again. Generate a new token, put it in your system and delete the old one.
- <b>The API answers that I cannot create or change orders.</b> The token is read only. Generate a token without <b>Read only</b> ticked.
- <b>The API refuses my requests for a short while.</b> Each token can make up to 120 requests a minute. Slow down your system and try again after a minute.
- <b>The API says "This order has no products yet".</b> Add at least one product to the order before you submit it.
- <b>The API says "Unable to find related portfolio item".</b> Through the API you add a product to an order by its <b>My Products</b> item, not by the product itself. Add the product to <b>My Products</b> first and use the id of that item.
- <b>The API says another transaction with the same product already exists.</b> The product is already on the order. Change the quantity of that line instead of adding it again.
- <b>The API says the order "is already in the 'submitted' state and cannot be updated".</b> Submitted orders cannot be changed or deleted through the API. Ask us in the chat on our website if the order must change.
- <b>My API order shows Unpaid.</b> Your balance and saved cards did not cover it. Top up your balance, open the order and press <b>Pay … with balance</b>, and check that your saved card is still valid.
