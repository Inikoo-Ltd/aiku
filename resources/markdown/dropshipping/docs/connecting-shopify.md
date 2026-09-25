---
title: Connecting your Shopify store
summary: Link your Shopify store to your dropshipping account with its myshopify.com name, install our app in Shopify, and fix a store that still says it is not connected.
date: 2026-09-25
tags: shopify, sales channel, connect, install, myshopify
category: sales-channels
series: shopify
order: 1
help_routes: retina.dropshipping.customer_sales_channels.index, retina.dropshipping.customer_sales_channels.create, retina.dropshipping.customer_sales_channels.show
shops: awd, dssk, dse
---

<aside class="tldr">
Go to <b>Channels</b>, press <b>Add Sales Channel</b>, then <b>Connect</b> on the Shopify card. Type your store's <b>myshopify.com</b> name, not your own domain, and press <b>Connect</b>. Shopify opens in a new tab: press <b>Install</b> there. The connection is only finished when the app is installed in Shopify.
</aside>

## Before you start

- You need the <b>myshopify.com</b> name of your store. Shopify gave it to you when you created the store, for example <b>mystore.myshopify.com</b> or a code like <b>ab12cd-3e.myshopify.com</b>. Your own domain, such as <b>www.mystore.com</b>, does not work.
- To find it, open your Shopify admin and go to <b>Settings</b>, <b>Domains</b>. You can also look at the address bar of your Shopify admin: in <b>admin.shopify.com/store/ab12cd-3e</b>, the name is <b>ab12cd-3e</b>.
- Log in to your Shopify admin in the same browser, as the store owner or a staff member who can install apps.
- Shopify is available on all our dropshipping websites. Connect it from the website where you have your dropshipping account.

## Connect your store

1. Open <b>Channels</b> in the menu. The <b>Sales Channels</b> page lists the channels you already have.
2. Press <b>Add Sales Channel</b>.
3. Find the <b>Shopify</b> card and press <b>Connect</b>. A window opens: <b>Please enter your Shopify unique domain name</b>.
4. Type the name of your store in the box. The end, <b>.myshopify.com</b>, is already written for you. You can also paste the full <b>xxx.myshopify.com</b> address or the <b>admin.shopify.com/store/...</b> address: we keep only the store name.
5. Press <b>Connect</b>.
6. Shopify opens in a new tab and asks you to install our app. Press <b>Install</b>. If no new tab opens, your browser blocked it: allow pop-ups for our website, or use <b>Click here to install</b> on the channel page (see below).
7. When the app is installed, Shopify shows the app page. You can close that tab and go back to your dropshipping account.

<!-- screenshot: the Shopify connect window with a store name typed and the .myshopify.com ending shown on the right -->

The new channel is now in your <b>Sales Channels</b> list. Open it to see its dashboard.

If you are not sure which is your store name, press the <b>Click here</b> link next to <b>Not sure which is your Shopify store name?</b> in the same window.

## What we set up in your store

When the app is installed we do these things in your Shopify store for you:

- We add a fulfilment location whose name starts with <b>aiku-</b>. Stock of the products you connect is kept in this location, and Shopify sends us the orders for those products through it.
- We add this location to your default shipping profile, so Shopify can sell and ship from it. See [The AW fulfilment location in Shopify](/docs/shopify-fulfilment-location).
- We set up the messages Shopify sends us when orders arrive.

You do not need to do any of this by hand.

## Check that the channel is connected

Open the channel from <b>Channels</b>. When our app is installed, three small icons appear next to the store name. Move your mouse over them to read their names:

- <b>App installed</b>: our app is installed and we can read your store.
- <b>Exist in platform</b> and <b>Platform status</b>: our fulfilment location is set up in your store.

When all three are green ticks, the dashboard shows the <b>Orders</b> and <b>Products</b> boxes and, on the left menu under your channel, <b>My Products</b> and <b>Orders</b>. Now you can add products: see [Managing products on Shopify](/docs/managing-products-on-shopify).

<!-- screenshot: channel dashboard with the three green ticks, the Fetch orders button and the Orders and Products boxes -->

## If it says the channel is not connected yet

If you see <b>Your channel is not connected yet to the platform. Please connect it to be able to synchronize your products.</b>, the app was not installed in Shopify. This is the most common problem. It happens when the Shopify tab was closed before pressing <b>Install</b>, or when your browser blocked the new tab.

1. Log in to your Shopify admin in the same browser.
2. On the channel page, click <b>Click here to install</b>, at the end of <b>Make sure you click the button "Install" in the Shopify dashboard to finalize the connection.</b>
3. Shopify opens in the same tab. Press <b>Install</b>.
4. Go back to the channel page and reload it.

If it still does not work, you can press <b>Delete</b> next to <b>Or delete the channel and try again</b>, then connect the store again from the start.

## Delete or reset a channel

- <b>Delete channel</b>: shown on a connected channel. It asks <b>Are you sure you want to delete channel</b>; press <b>Yes, delete channel</b> to confirm. If you connect the same Shopify store again later, we can reopen the old channel with its products instead of making a new one.
- <b>Reset channel</b>: shown when the channel lost its connection but still has products. It sets up the fulfilment location and the order messages again. Your products must then be linked again. Orders already made are not changed.

## When something goes wrong

**"This does not look like a Shopify store name. Use the .myshopify.com name, not your own domain."** You typed your own domain, such as <b>mystore.com</b>. Type the <b>myshopify.com</b> name instead. You find it in Shopify under <b>Settings</b>, <b>Domains</b>.

**"Shopify shop ... not found".** No Shopify store has that name. Check the spelling. The name is often a code of letters and numbers, not your shop name.

**"Shopify shop ... already exists, please use other name".** This store is already connected to a dropshipping account. Check your <b>Sales Channels</b> list. If it is connected to another account of yours, delete it there first.

**"Shop name cannot contain spaces".** The myshopify.com name never has spaces. Copy it from Shopify instead of typing your shop name.

**I connected Shopify but it still says not connected.** The app was not installed. Follow the steps in <b>If it says the channel is not connected yet</b> above.

**"Click here to install" shows "Something went wrong".** The channel lost its link to your store. Press <b>Delete</b> next to <b>Or delete the channel and try again</b>, then connect the store again.

**After pressing Connect, no Shopify tab opens.** Your browser blocked the new tab. Allow pop-ups for our website, or open the channel and use <b>Click here to install</b>.

**Products do not sell in Shopify, or show as sold out.** Check that the <b>aiku-</b> location is in your shipping profile. See [The AW fulfilment location in Shopify](/docs/shopify-fulfilment-location).

If none of this helps, ask us in the chat on our website and tell us your myshopify.com name.

<aside class="wayfinder"><strong>Where to click</strong>
<ul>
<li><b>Connect a new store:</b> <b>Channels</b> → <b>Add Sales Channel</b> → <b>Shopify</b> → <b>Connect</b>.</li>
<li><b>Finish an install:</b> open the channel → <b>Click here to install</b> → <b>Install</b> in Shopify.</li>
<li><b>Check the connection:</b> open the channel → the three icons next to its name.</li>
<li><b>Change stock settings:</b> open the channel → <b>Manage Sales Channel</b>.</li>
</ul>
</aside>
